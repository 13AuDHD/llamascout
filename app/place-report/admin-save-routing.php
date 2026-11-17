<?php

declare(strict_types=1);

/**
 * One target kind per normal Admin report save.
 *
 * A mapped Facility uses the existing Area-scoped writer. A linked Facility
 * uses the separate save-linked-facility action, and Resources have their own
 * Admin editor. Photo-only requests use a separately verified gallery owner.
 *
 * Legacy/missing report modes fail closed instead of saving Place + Site +
 * Area in one request.
 *
 * @return list<'place'|'site'|'area'>
 */
function llama_admin_report_save_targets(string $mode, bool $photoOnly = false): array
{
    if ($photoOnly) {
        return [];
    }

    return match ($mode) {
        'place' => ['place'],
        'site' => ['site'],
        'facility', 'area' => ['area'],
        default => throw new InvalidArgumentException(
            'Select one Place, Site, or Facility report before saving.'
        ),
    };
}
