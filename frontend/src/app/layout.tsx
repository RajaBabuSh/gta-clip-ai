import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "GTA CLIP AI - AI Gaming Highlights & Shorts Studio",
  description: "Convert long-form gaming footage and streams into high-energy vertical shorts with AI moment detection and Whisper subtitles.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className="dark">
      <body className="bg-zinc-950 text-zinc-100 antialiased selection:bg-rose-500 selection:text-white">
        {children}
      </body>
    </html>
  );
}
