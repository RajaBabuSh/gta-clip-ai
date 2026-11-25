import math
import re
from typing import Dict, Any, List, Optional

EXCITEMENT_KEYWORDS = {
    "police": ["cop", "cops", "police", "star", "stars", "chase", "busted", "siren", "wanted", "sirens"],
    "fail": ["fail", "failed", "died", "dead", "wasted", "crash", "crashed", "wrecked", "stupid", "lost", "bust"],
    "funny": ["lol", "lmao", "haha", "hahaha", "funny", "bro", "bruh", "what", "no way", "are you serious", "wtf"],
    "action": ["shoot", "shot", "gun", "rpg", "kill", "explosion", "boom", "run", "jump", "speed", "drift", "blast"],
    "clutch": ["omg", "holy", "insane", "clutch", "saved", "escaped", "lets go", "letsgo", "clutching", "epic"]
}

GENRE_KEYWORDS = {
    "gaming": EXCITEMENT_KEYWORDS,
    "podcast": {
        "insight": ["truth", "secret", "realize", "discovered", "lesson", "mindset", "success", "mistake", "money"],
        "shock": ["shocking", "unbelievable", "never told", "insane", "crazy", "confession", "dark", "scandal"],
        "debate": ["disagree", "actually", "wrong", "argument", "point", "perspective", "evidence", "proof"]
    },
    "reaction": {
        "disbelief": ["no way", "wait what", "are you kidding", "oh my god", "what the", "look at that", "unreal"],
        "laughter": ["haha", "hahaha", "wheezing", "dead", "crying", "dying", "lmao", "lol"],
        "hype": ["lets go", "yooo", "hyped", "insane", "clip that", "clip it"]
    },
    "tutorial": {
        "key_step": ["step", "first", "important", "remember", "trick", "hack", "how to", "technique", "method"],
        "warning": ["don't do this", "avoid", "mistake", "careful", "crucial", "essential", "key"]
    }
}

VIRAL_TITLE_TEMPLATES = [
    "Cops Had No Idea What Was Coming 🚔",
    "Funniest GTA Moment You'll See Today 😂",
    "He Really Thought He Could Escape 💀",
    "Instant Karma in GTA 5 ⚡",
    "When You Try To Be A Pro Gamer 🤦‍♂️",
    "The Most Insane GTA Escape Ever 🚀",
    "Wait For The Ending... 🤯",
    "This Streamer Completely Lost It 😭",
    "He Did NOT See That Coming 💥",
    "Absolute Chaos in Los Santos 🚁",
    "1000 IQ GTA 5 Play 🧠",
    "Bro Did Not Realize What Happened 💀",
    "Craziest Cop Chase In GTA V 🏎️",
    "I Can't Believe This Actually Worked 🔥",
    "Pure Comedy In GTA 5 🤣",
    "Epic Heist Gone Completely Wrong 💰",
    "When The NPC Fights Back 👊",
    "Nobody Survived This Incident ⚠️",
    "Most Unhinged GTA Moment Ever 🌪️",
    "GTA Online In A Nutshell 💀"
]

class SmartMomentEngine:
    @staticmethod
    def analyze_chunk(
        chunk_start: float,
        chunk_end: float,
        audio_analysis: Dict[str, Any],
        transcript_data: Dict[str, Any],
        genre: str = "gaming"
    ) -> List[Dict[str, Any]]:
        """
        Analyzes a single audio/transcript chunk and detects candidate excitement moments.
        Timestamps are offset by chunk_start to align with the full video timeline.
        Supports genre presets: 'gaming', 'podcast', 'reaction', 'tutorial'.
        """
        timeline = audio_analysis.get("timeline", [])
        segments = transcript_data.get("segments", [])
        chunk_duration = max(1.0, chunk_end - chunk_start)

        genre_dict = GENRE_KEYWORDS.get(genre, EXCITEMENT_KEYWORDS)

        grid_step = 1.0
        n_steps = int(math.ceil(chunk_duration / grid_step))
        scores = [0.0] * n_steps

        # 1. Acoustic Energy Timeline
        for item in timeline:
            idx = int(item["time"] / grid_step)
            if idx < n_steps:
                energy = item.get("energy", 0.0)
                peak_bonus = 0.40 if item.get("is_peak") else 0.0
                scores[idx] += (energy * 0.5) + peak_bonus

        # 2. Speech Velocity and Excitement Keyword Scanning
        for seg in segments:
            seg_start = seg.get("start", 0)
            seg_end = seg.get("end", 0)
            text_lower = seg.get("text", "").lower()
            words = seg.get("words", [])
            seg_duration = max(0.4, seg_end - seg_start)
            speech_rate = len(words) / seg_duration

            kw_bonus = 0.0
            for category, kws in genre_dict.items():
                for kw in kws:
                    if kw in text_lower:
                        kw_bonus += 0.30

            if "!" in seg.get("text", ""):
                kw_bonus += 0.25
            if "?" in seg.get("text", ""):
                kw_bonus += 0.15

            start_idx = max(0, min(n_steps - 1, int(seg_start / grid_step)))
            end_idx = max(0, min(n_steps, int(seg_end / grid_step) + 1))
            for i in range(start_idx, end_idx):
                scores[i] += min(1.2, (speech_rate * 0.18) + kw_bonus)

        # 3. Detect candidate peak anchors
        candidates = []
        for i in range(1, n_steps - 1):
            if scores[i] >= scores[i - 1] and scores[i] >= scores[i + 1] and scores[i] > 0.15:
                abs_time = round(chunk_start + (i * grid_step), 2)
                candidates.append({
                    "peak_time": abs_time,
                    "score": round(scores[i], 3),
                    "chunk_start": chunk_start,
                    "chunk_end": chunk_end
                })

        # If sparse speech/audio, add at least one representative anchor
        if not candidates and chunk_duration >= 8.0:
            candidates.append({
                "peak_time": round(chunk_start + (chunk_duration / 2), 2),
                "score": 0.45,
                "chunk_start": chunk_start,
                "chunk_end": chunk_end
            })

        # Attach local word slices for subtitle generation
        for cand in candidates:
            p_time = cand["peak_time"]
            # Context window around peak: -6s to +18s
            w_start = max(chunk_start, p_time - 6.0)
            w_end = min(chunk_end, p_time + 18.0)

            cand_words = []
            cand_text_parts = []
            for seg in segments:
                abs_seg_start = chunk_start + seg.get("start", 0)
                abs_seg_end = chunk_start + seg.get("end", 0)
                if abs_seg_end >= w_start and abs_seg_start <= w_end:
                    cand_text_parts.append(seg.get("text", ""))
                    for w in seg.get("words", []):
                        abs_w_start = chunk_start + w.get("start", 0)
                        abs_w_end = chunk_start + w.get("end", 0)
                        if abs_w_start >= w_start and abs_w_end <= w_end:
                            cand_words.append({
                                "word": w.get("word", "").strip(),
                                "start": round(abs_w_start, 2),
                                "end": round(abs_w_end, 2)
                            })

            cand["text"] = " ".join(cand_text_parts).strip()
            cand["words"] = cand_words

        return candidates

    @staticmethod
    def consolidate_and_rank_moments(
        candidate_moments: List[Dict[str, Any]],
        total_duration: float,
        target_count: int = 10,
        min_clip_duration: float = 12.0,
        max_clip_duration: float = 38.0
    ) -> Dict[str, Any]:
        """
        Consolidates candidate moments across all video chunks.
        Applies adaptive duration scaling, dynamic boundary fitting,
        and multi-pass distinct peak anchor selection to fulfill the requested target_count.
        """
        if not candidate_moments:
            return {
                "moments": [],
                "target_count": target_count,
                "actual_count": 0,
                "candidates_detected": 0,
                "candidates_accepted": 0,
                "candidates_rejected": 0,
                "notice": "No candidate moments detected across source media."
            }

        # Calculate the actual analyzed bounds
        max_cand_time = max([c.get("chunk_end", c.get("peak_time", 0.0)) for c in candidate_moments] + [60.0])
        effective_footage = max(10.0, min(total_duration, max_cand_time))

        # Adaptive duration based on target count and available footage
        if target_count >= 15:
            target_clip_len = max(8.0, min(14.0, effective_footage / (target_count * 1.1)))
            min_clip_len = max(5.0, target_clip_len * 0.7)
        elif target_count >= 10:
            target_clip_len = max(10.0, min(18.0, effective_footage / (target_count * 1.05)))
            min_clip_len = max(6.0, target_clip_len * 0.7)
        elif target_count >= 5:
            target_clip_len = max(12.0, min(24.0, effective_footage / (target_count * 1.0)))
            min_clip_len = max(8.0, target_clip_len * 0.7)
        else:
            target_clip_len = max(14.0, min(30.0, effective_footage / (target_count * 0.9)))
            min_clip_len = max(8.0, target_clip_len * 0.65)

        min_gap = 0.5
        sorted_cands = sorted(candidate_moments, key=lambda c: c.get("score", 0.0), reverse=True)
        chosen_moments = []
        used_ranges = []

        # Pass 1: Smart Non-Overlapping with dynamic boundary fitting
        for cand in sorted_cands:
            if len(chosen_moments) >= target_count:
                break
            peak_t = cand["peak_time"]
            words = cand.get("words", [])

            # Find empty window around peak between existing clips
            left_bound = 0.0
            right_bound = effective_footage
            inside_existing = False

            for (us, ue) in used_ranges:
                if us <= peak_t <= ue:
                    inside_existing = True
                    break
                if ue < peak_t:
                    left_bound = max(left_bound, ue + min_gap)
                if us > peak_t:
                    right_bound = min(right_bound, us - min_gap)

            if inside_existing:
                continue

            avail_span = right_bound - left_bound
            if avail_span >= min_clip_len:
                lead_in = min(3.5, max(1.5, (peak_t - left_bound) * 0.45))
                c_start = max(left_bound, round(peak_t - lead_in, 2))
                c_end = min(right_bound, round(c_start + target_clip_len, 2))
                if c_end - c_start < min_clip_len:
                    c_start = max(left_bound, round(c_end - min_clip_len, 2))

                # Snap to words if available within bounds
                if words:
                    for w in words:
                        if abs(w["start"] - c_start) <= 2.0 and w["start"] >= left_bound:
                            c_start = w["start"]
                            break
                    for w in reversed(words):
                        if abs(w["end"] - c_end) <= 2.5 and w["end"] <= right_bound and w["end"] > c_start + min_clip_len:
                            c_end = w["end"]
                            break

                c_dur = round(c_end - c_start, 2)
                if c_dur >= min_clip_len:
                    used_ranges.append((round(c_start, 2), round(c_end, 2)))

                    rel_words = []
                    for w in words:
                        if w["start"] >= c_start and w["end"] <= c_end:
                            rel_words.append({
                                "word": w["word"],
                                "start": max(0.0, round(w["start"] - c_start, 2)),
                                "end": max(0.0, round(w["end"] - c_start, 2))
                            })

                    score = cand.get("score", 0.5)
                    virality = round(min(99.0, max(75.0, (score * 18.0) + 70.0)), 1)
                    chosen_moments.append({
                        "start_time": round(c_start, 2),
                        "end_time": round(c_end, 2),
                        "duration": c_dur,
                        "peak_time": peak_t,
                        "virality_score": virality,
                        "text": cand.get("text", ""),
                        "words": rel_words
                    })

        # Pass 2: Distinct Secondary Peak Pass if target not reached
        if len(chosen_moments) < target_count:
            for cand in sorted_cands:
                if len(chosen_moments) >= target_count:
                    break
                peak_t = cand["peak_time"]
                # Must be distinct peak at least 3.5s away from any chosen peak
                if any(abs(m["peak_time"] - peak_t) < 3.5 for m in chosen_moments):
                    continue

                lead_in = min(3.0, target_clip_len * 0.35)
                c_start = max(0.0, round(peak_t - lead_in, 2))
                c_end = min(effective_footage, round(c_start + target_clip_len, 2))
                c_dur = round(c_end - c_start, 2)

                words = cand.get("words", [])
                rel_words = []
                for w in words:
                    if w["start"] >= c_start and w["end"] <= c_end:
                        rel_words.append({
                            "word": w["word"],
                            "start": max(0.0, round(w["start"] - c_start, 2)),
                            "end": max(0.0, round(w["end"] - c_start, 2))
                        })

                if c_dur >= min_clip_len and not any(m["start_time"] == c_start and m["end_time"] == c_end for m in chosen_moments):
                    score = cand.get("score", 0.4)
                    virality = round(min(98.0, max(75.0, (score * 16.0) + 68.0)), 1)
                    chosen_moments.append({
                        "start_time": c_start,
                        "end_time": c_end,
                        "duration": c_dur,
                        "peak_time": peak_t,
                        "virality_score": virality,
                        "text": cand.get("text", ""),
                        "words": rel_words
                    })

        # Pass 3: Systematic Timeline Coverage if still below target
        if len(chosen_moments) < target_count and effective_footage > 0:
            grid_count = max(target_count * 3, 30)
            step = effective_footage / float(grid_count)
            for idx in range(grid_count):
                if len(chosen_moments) >= target_count:
                    break
                anchor_t = round((idx + 0.5) * step, 2)
                if any(abs(m["peak_time"] - anchor_t) < 1.5 for m in chosen_moments):
                    continue
                c_start = max(0.0, round(anchor_t - (target_clip_len * 0.5), 2))
                c_end = min(effective_footage, round(c_start + target_clip_len, 2))
                c_dur = round(c_end - c_start, 2)
                if c_dur >= min_clip_len and not any(abs(m["start_time"] - c_start) < 1.0 and abs(m["end_time"] - c_end) < 1.0 for m in chosen_moments):
                    chosen_moments.append({
                        "start_time": c_start,
                        "end_time": c_end,
                        "duration": c_dur,
                        "peak_time": anchor_t,
                        "virality_score": 80.0,
                        "text": "Gameplay Action Sequence",
                        "words": []
                    })

        # Sort final moments by virality score descending
        chosen_moments.sort(key=lambda m: m["virality_score"], reverse=True)

        # Generate titles, captions, and hashtags
        final_clips = []
        for index, moment in enumerate(chosen_moments[:target_count]):
            title_template = VIRAL_TITLE_TEMPLATES[index % len(VIRAL_TITLE_TEMPLATES)]
            text_lower = moment["text"].lower()

            if any(k in text_lower for k in EXCITEMENT_KEYWORDS["police"]):
                title = f"GTA 5 Police Chase Gone Wrong 🚔 [Part {index + 1}]"
            elif any(k in text_lower for k in EXCITEMENT_KEYWORDS["fail"]):
                title = f"The Biggest GTA 5 Fail Yet 💀 [Part {index + 1}]"
            elif any(k in text_lower for k in EXCITEMENT_KEYWORDS["clutch"]):
                title = f"Insane GTA Clutch Moment 🔥 [Part {index + 1}]"
            elif any(k in text_lower for k in EXCITEMENT_KEYWORDS["funny"]):
                title = f"Funniest GTA Online Encounter 😂 [Part {index + 1}]"
            else:
                title = f"{title_template} [Clip {index + 1}]"

            hashtags = ["#GTAV", "#GTA5", "#GamingShorts", "#GTAOnline", "#GamingHighlights", "#Shorts", "#Reels"]

            caption = (
                f"{title}\n\n"
                f"Watch until the end! Best moments from the latest GTA gameplay. "
                f"Drop a follow for more viral gaming clips daily! 🎮🔥\n\n"
                f"{' '.join(hashtags[:5])}"
            )

            final_clips.append({
                "clip_index": index + 1,
                "start_time": moment["start_time"],
                "end_time": moment["end_time"],
                "duration": moment["duration"],
                "virality_score": moment["virality_score"],
                "title": title,
                "description": caption,
                "hashtags": hashtags,
                "words": moment["words"],
                "transcript_excerpt": moment["text"][:150]
            })

        notice = None
        if len(final_clips) < target_count:
            notice = f"Analyzed video footage contains {len(final_clips)} distinct entertaining moments without artificial duplication."

        return {
            "moments": final_clips,
            "target_count": target_count,
            "actual_count": len(final_clips),
            "candidates_detected": len(candidate_moments),
            "candidates_accepted": len(final_clips),
            "candidates_rejected": max(0, len(candidate_moments) - len(final_clips)),
            "notice": notice
        }

    @classmethod
    def identify_moments(
        cls,
        total_duration: float,
        audio_analysis: Dict[str, Any],
        transcript_data: Dict[str, Any],
        target_count: int = 10,
        min_clip_duration: float = 12.0,
        max_clip_duration: float = 38.0
    ) -> Dict[str, Any]:
        """
        Legacy single-pass helper for shorter videos (< 10 minutes).
        Wraps analyze_chunk and consolidate_and_rank_moments.
        """
        cands = cls.analyze_chunk(0.0, total_duration, audio_analysis, transcript_data)
        return cls.consolidate_and_rank_moments(
            candidate_moments=cands,
            total_duration=total_duration,
            target_count=target_count,
            min_clip_duration=min_clip_duration,
            max_clip_duration=max_clip_duration
        )
