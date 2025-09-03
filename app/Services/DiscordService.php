<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DiscordService
{
    protected string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.discord.webhook_url');
    }

    public function sendStreamerPlan(array $streamer, array $plan, array $plannedStreams): void
    {
        $embed = [
            'title' => "📢 Streamer Plan Notification",
            'color' => hexdec('5865F2'), // Discord blurple
            'fields' => [
                [
                    'name' => '👤 Streamer',
                    'value' => "**Name:** {$streamer['name']}\n**Username:** {$streamer['username']}",
                    'inline' => false,
                ],
                [
                    'name' => '📊 Plan Info',
                    'value' => "**Views/day:** {$plan['views']}\n**Chats/day:** {$plan['chats']}\n**Hours/day:** {$plan['hours']}",
                    'inline' => false,
                ],
                [
                    'name' => '🗓 Planned Streams',
                    'value' => $this->formatPlannedStreams($plannedStreams),
                    'inline' => false,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        Http::post($this->webhookUrl, [
            'embeds' => [$embed],
        ]);
    }

    protected function formatPlannedStreams(array $plannedStreams): string
    {
        if (empty($plannedStreams)) {
            return "_No planned streams_";
        }

        return collect($plannedStreams)
            ->map(fn ($s) => "**{$s['name']}**\nStart: {$s['start_date']}\nDuration: {$s['duration']} hrs")
            ->implode("\n\n");
    }
}
