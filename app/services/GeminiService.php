<?php
// app/services/GeminiService.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

class GeminiService {
    private static ?string $cachedApiKey = null;
    private static ?string $cachedModel = null;

    public static function getApiKey(): ?string {
        if (self::$cachedApiKey !== null) {
            return self::$cachedApiKey;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val) {
                self::$cachedApiKey = trim((string)$val);
                return self::$cachedApiKey;
            }
        } catch (Exception $e) {
            // fallback
        }

        self::$cachedApiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: null;
        return self::$cachedApiKey;
    }

    public static function getModel(): string {
        if (self::$cachedModel !== null) {
            return self::$cachedModel;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_model' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val) {
                self::$cachedModel = trim((string)$val);
                return self::$cachedModel;
            }
        } catch (Exception $e) {
            // fallback
        }

        self::$cachedModel = $_ENV['GEMINI_MODEL'] ?? getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash';
        return self::$cachedModel;
    }

    public static function isEnabled(): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_enabled' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                return (string)$val === '1' || strtolower((string)$val) === 'true';
            }
        } catch (Exception $e) {}
        return !empty(self::getApiKey());
    }

    public static function verifyApiKey(?string $apiKey = null): array {
        $key = $apiKey ?: self::getApiKey();
        if (empty($key)) {
            return [
                'success' => false,
                'message' => 'No Gemini API Key provided. Please enter a valid API key in Admin API Settings.',
                'models' => []
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'message' => "Connection failed: $err", 'models' => []];
        }

        $json = json_decode((string)$response, true);
        if ($httpCode !== 200) {
            $msg = $json['error']['message'] ?? "Google API returned HTTP $httpCode";
            return ['success' => false, 'message' => $msg, 'models' => []];
        }

        $models = [];
        if (!empty($json['models'])) {
            foreach ($json['models'] as $m) {
                $name = str_replace('models/', '', $m['name'] ?? '');
                if (str_contains($name, 'gemini')) {
                    $models[] = [
                        'name' => $name,
                        'displayName' => $m['displayName'] ?? $name,
                        'description' => $m['description'] ?? '',
                        'inputTokenLimit' => $m['inputTokenLimit'] ?? null
                    ];
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Gemini API connection successful! Retrieved ' . count($models) . ' compatible models.',
            'models' => $models
        ];
    }

    public static function generateSeoMetadata(string $clipTitle, string $transcript = '', array $extraTags = [], ?string $userId = null): array {
        $apiKey = self::getApiKey();
        $model = self::getModel();

        if (self::isEnabled() && !empty($apiKey)) {
            $prompt = "You are an expert viral YouTube Shorts and TikTok video strategist for GTA and gaming content.\n" .
                      "Given the clip title: \"$clipTitle\"\n" .
                      "And dialogue snippet: \"$transcript\"\n\n" .
                      "Generate an optimized JSON object strictly with these keys:\n" .
                      "{\n" .
                      "  \"youtube_title\": \"(Catchy, under 60 chars with 1-2 gaming emojis)\",\n" .
                      "  \"instagram_caption\": \"(Engaging hook + call to action, 100-150 chars)\",\n" .
                      "  \"description\": \"(Comprehensive description with summary and credits)\",\n" .
                      "  \"hashtags\": [\"#GTAV\", \"#GTA5\", \"#GamingShorts\", \"#Shorts\", \"#ViralGaming\"],\n" .
                      "  \"keywords\": [\"gta 5 funny moments\", \"gta highlights\", \"streamer fails\"],\n" .
                      "  \"suggested_category\": \"Gaming\",\n" .
                      "  \"thumbnail_text\": \"(Short punchy 2-4 word text for video thumbnail overlay)\"\n" .
                      "}\nReturn ONLY the JSON.";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
            $payload = [
                "contents" => [
                    ["parts" => [["text" => $prompt]]]
                ],
                "generationConfig" => [
                    "temperature" => 0.7,
                    "maxOutputTokens" => 800
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $resData = json_decode($response, true);
                $rawText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                // Extract json block
                if (preg_match('/\{[\s\S]*\}/', $rawText, $matches)) {
                    $parsed = json_decode($matches[0], true);
                    if ($parsed && !empty($parsed['youtube_title'])) {
                        self::recordApiUsage($userId, 'gemini', 'generate_seo', 600, 0.00015);
                        return $parsed;
                    }
                }
            }
        }

        // High-Quality Algorithmic Local Fallback
        return self::generateLocalSeoFallback($clipTitle, $transcript, $extraTags);
    }

    private static function generateLocalSeoFallback(string $title, string $transcript, array $extraTags): array {
        $cleanTitle = trim(preg_replace('/\[Clip \d+\]/i', '', $title));
        $emojis = ['🔥', '💀', '😱', '🚨', '💥', '🤯'];
        $emoji = $emojis[array_rand($emojis)];

        $ytTitle = mb_substr($cleanTitle, 0, 50) . " $emoji #Shorts";
        $caption = "Wait till the end! 🎮🔥 What would you have done here? Drop your reaction in the comments! Follow for daily GTA clips! 🚀";
        $desc = "$cleanTitle\n\nWatch until the end for the craziest gaming moments! Drop a follow and subscribe for daily viral GTA and gaming highlights! 🎮🔥\n\n#GTAV #GTA5 #GamingShorts #Shorts #Reels #ViralGaming";
        
        $tags = array_unique(array_merge([
            "#GTAV", "#GTA5", "#GamingShorts", "#Shorts", "#ViralGaming", "#GamingClips", "#TwitchHighlights"
        ], $extraTags));

        $keywords = [
            "gta 5", "gta v", "gta funny moments", "gta fails", "gta police chase", "gaming highlights", "shorts"
        ];

        return [
            'youtube_title' => $ytTitle,
            'instagram_caption' => $caption,
            'description' => $desc,
            'hashtags' => array_values($tags),
            'keywords' => $keywords,
            'suggested_category' => 'Gaming',
            'thumbnail_text' => 'NO WAY! 💀'
        ];
    }

    public static function recordApiUsage(?string $userId, string $service, string $operation, int $tokens = 0, float $cost = 0.0): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO api_usage (id, user_id, service, operation, tokens_used, units_consumed, estimated_cost_usd, status_code, created_at)
                VALUES (UUID(), ?, ?, ?, ?, 1, ?, 200, NOW())
            ");
            $stmt->execute([$userId, $service, $operation, $tokens, $cost]);
        } catch (Exception $e) {
            // non-fatal
        }
    }
}
