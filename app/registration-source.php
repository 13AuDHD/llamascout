<?php

declare(strict_types=1);

/*
 * Registration acquisition sources.
 *
 * Keep the stored values stable. Labels may be changed later without
 * rewriting historical user records.
 */

function llama_registration_sources(): array
{
    return [
        'search' => 'Search engine (Google, Bing, etc.)',
        'facebook_instagram' => 'Facebook or Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'reddit' => 'Reddit',
        'podcast' => 'Podcast',
        'ai_tools' => 'AI tools (ChatGPT, Gemini, etc.)',
        'friend_referral' => 'A friend referred me',
        'website_blog' => 'Another website or blog',
        'event_community' => 'Event, club, or outdoor community',
        'other' => 'Other',
    ];
}

function llama_registration_source_is_valid(
    string $source
): bool {
    return array_key_exists(
        $source,
        llama_registration_sources()
    );
}

function llama_registration_source_label(
    ?string $source
): string {
    $source = trim((string) $source);

    if ($source === '') {
        return 'Not supplied';
    }

    return llama_registration_sources()[$source]
        ?? 'Other';
}
