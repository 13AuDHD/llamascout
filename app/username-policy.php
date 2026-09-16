<?php

declare(strict_types=1);

/*
 * Llama Scout username policy.
 *
 * Usernames are already limited elsewhere to:
 *   a-z, 0-9, underscore
 *   4-16 characters
 *
 * This file adds:
 *   - reserved / staff-like usernames
 *   - brand impersonation protection
 *   - common profanity / abusive username blocking
 *   - simple separator and leetspeak normalization
 *
 * Keep this file as the single source of truth.
 */


/* =========================================================
   NORMALIZATION
   ========================================================= */

function username_policy_normalize(
    string $username
): string {

    $username =
        strtolower(
            trim($username)
        );

    /*
     * Remove separators so names such as
     * llama_admin and llamaadmin are compared
     * the same way.
     */

    $compact =
        preg_replace(
            '/[^a-z0-9]/',
            '',
            $username
        );

    return
        is_string($compact)
            ? $compact
            : '';
}


function username_policy_leet(
    string $username
): string {

    $compact =
        username_policy_normalize(
            $username
        );

    return strtr(
        $compact,
        [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 't',
        ]
    );
}


/*
 * Preserve underscores for terms that are unsafe to match as arbitrary
 * substrings. This lets the policy distinguish a real component such as
 * "go_to_hell" from harmless words such as "shellfish".
 */
function username_policy_component_form(
    string $username,
    bool $leet = false
): string {

    $value =
        strtolower(
            trim($username)
        );

    $value =
        preg_replace(
            '/[^a-z0-9_]/',
            '',
            $value
        );

    if (!is_string($value)) {
        return '';
    }

    if (!$leet) {
        return $value;
    }

    return strtr(
        $value,
        [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 't',
        ]
    );
}


/* =========================================================
   RESERVED EXACT NAMES
   ========================================================= */

function username_policy_reserved_names(): array
{
    return [

        /*
         * Administrative / privileged.
         */

        'admin',
        'administrator',
        'admins',
        'staff',
        'team',
        'moderator',
        'moderators',
        'mod',
        'mods',
        'support',
        'help',
        'helpdesk',
        'security',
        'system',
        'root',
        'owner',
        'founder',
        'official',
        'webmaster',
        'billing',
        'accounts',
        'account',
        'membership',
        'memberships',
        'contact',
        'info',
        'legal',
        'privacy',
        'abuse',
        'reports',
        'report',
        'scout',
        'scouts',

        /*
         * Llama Scout identity / likely impersonation.
         */

        'llamascout',
        'thellamascout',
        'llamascouts',
        'llamascoutofficial',
        'officialllamascout',
        'llamascoutteam',
        'llamascoutstaff',
        'llamascoutadmin',
        'llamascoutsupport',
        'llamascoutmod',
        'llamascoutmoderator',
        'llamascoutsecurity',
        'llamascouthelp',
        'llamaadmin',
        'llamastaff',
        'llamasupport',
        'llamamod',
        'llamamoderator',
        'llamaofficial',
        'llamasecurity',
        'llamahelp',
    ];
}


/* =========================================================
   STAFF / IMPERSONATION TERMS
   ========================================================= */

function username_policy_privileged_terms(): array
{
    return [
        'admin',
        'administrator',
        'staff',
        'support',
        'moderator',
        'mod',
        'official',
        'security',
        'owner',
        'founder',
        'helpdesk',
        'webmaster',
    ];
}


function username_policy_brand_terms(): array
{
    return [
        'llama',
        'llamascout',
        'scout',
    ];
}


/* =========================================================
   INAPPROPRIATE TERMS
   ========================================================= */

function username_policy_blocked_terms(): array
{
    /*
     * Strong fragments.
     *
     * These are uncommon enough inside innocent words that they can be
     * checked anywhere in the compact username. That also catches obvious
     * concatenation and separator / leetspeak evasion.
     */

    return [

        /*
         * Profanity / abusive insults.
         */

        'fuck',
        'motherfucker',
        'shit',
        'bullshit',
        'bitch',
        'cunt',
        'dickhead',
        'asshole',
        'arsehole',
        'bastard',
        'slut',
        'whore',
        'douchebag',
        'jackass',

        /*
         * Explicit sexual-content terms.
         */

        'porn',
        'porno',
        'blowjob',
        'handjob',
        'rimjob',
        'gangbang',
        'dildo',
        'boobs',
        'tits',
        'rapist',
        'incest',
        'bestiality',
        'necrophilia',

        /*
         * Common slurs that are sufficiently distinctive for
         * substring matching. Identity words themselves are not
         * blocked simply for naming an identity.
         */

        'nigger',
        'nigga',
        'faggot',
        'tranny',
        'kike',
        'wetback',
        'beaner',
        'gook',
        'raghead',
        'sandnigger',

        /*
         * Extremist / hate identifiers and slogans.
         */

        'nazi',
        'neonazi',
        'hitler',
        'kkk',
        'whitepower',
        'heilhitler',
        'siegheil',
        'swastika',
    ];
}


function username_policy_ambiguous_terms(): array
{
    /*
     * These can occur inside ordinary words, names, technical terms,
     * geographic names, or surnames.
     *
     * Examples:
     *   shellfish
     *   analysis / analyst / canal
     *   sexton
     *   classy
     *   dickinson
     *   cocktail / peacock
     *   cumberland
     *   raccoon
     *   spice
     *   grape
     *   pedometer
     *   fire_retardant
     *
     * They are therefore NOT safe for unconditional substring blocking.
     */

    return [
        'hell',
        'anal',
        'sex',
        'ass',
        'dick',
        'cock',
        'pussy',
        'cum',
        'fag',
        'spic',
        'coon',
        'chink',
        'dyke',
        'retard',
        'pedo',
        'rape',
    ];
}


function username_policy_contains_ambiguous_term(
    string $username,
    string $term
): bool {

    /*
     * A term is explicit when letters do not immediately surround it.
     * Digits and underscores count as boundaries.
     *
     * Examples blocked:
     *   go_to_hell
     *   sex_69
     *   4nal_lover
     *   rape123
     *
     * Examples allowed:
     *   shellfish
     *   analysis
     *   sexton
     *   grapevine
     */

    $pattern =
        '/(?<![a-z])'
        . preg_quote(
            $term,
            '/'
        )
        . '(?![a-z])/';

    foreach (
        [
            username_policy_component_form(
                $username,
                false
            ),
            username_policy_component_form(
                $username,
                true
            ),
        ]
        as $form
    ) {
        if (
            $form !== ''
            && preg_match(
                $pattern,
                $form
            ) === 1
        ) {
            return true;
        }
    }

    return false;
}


function username_policy_ambiguous_hit_count(
    string $username
): int {

    /*
     * One ambiguous fragment may simply be part of an innocent word.
     * Multiple ambiguous fragments in the same compact username are a
     * strong signal that the username is intentionally combining them.
     *
     * This closes concatenation such as:
     *   hellsexanal
     *   h3lls3x4nal
     *   sexsex
     *
     * without rejecting a single ordinary occurrence such as analysis.
     */

    $scan =
        username_policy_leet(
            $username
        );

    if ($scan === '') {
        return 0;
    }

    $hits = 0;

    foreach (
        username_policy_ambiguous_terms()
        as $term
    ) {
        $hits +=
            substr_count(
                $scan,
                $term
            );

        if ($hits >= 2) {
            return $hits;
        }
    }

    return $hits;
}


/* =========================================================
   POLICY CHECK
   ========================================================= */

function username_policy_check(
    string $username
): array {

    $username =
        strtolower(
            trim($username)
        );


    /*
     * Basic format remains here too so callers can use
     * one function if desired.
     */

    if (
        !preg_match(
            '/^[a-z0-9_]{4,16}$/',
            $username
        )
    ) {

        return [
            'allowed' => false,
            'reason' =>
                'Username must be 4-16 characters and contain only letters, numbers, or underscores.',
            'code' => 'format',
        ];
    }


    $compact =
        username_policy_normalize(
            $username
        );

    $leet =
        username_policy_leet(
            $username
        );


    /*
     * Exact reserved names.
     */

    foreach (
        username_policy_reserved_names()
        as $reserved
    ) {

        if (
            $compact === $reserved
            ||
            $leet === $reserved
        ) {

            return [
                'allowed' => false,
                'reason' =>
                    'That username is reserved. Please choose another username.',
                'code' => 'reserved',
            ];
        }
    }


    /*
     * Prevent names that combine Llama Scout branding
     * with a privileged / official-looking term.
     *
     * Examples blocked:
     *   llama_admin
     *   adminllama
     *   llamascoutstaff
     *   official_llamascout
     *
     * Examples allowed:
     *   llamalover
     *   llamafan
     *   llamatrails
     */

    $hasBrandTerm = false;
    $hasPrivilegedTerm = false;


    foreach (
        username_policy_brand_terms()
        as $term
    ) {

        if (
            str_contains(
                $compact,
                $term
            )
            ||
            str_contains(
                $leet,
                $term
            )
        ) {

            $hasBrandTerm = true;
            break;
        }
    }


    foreach (
        username_policy_privileged_terms()
        as $term
    ) {

        if (
            str_contains(
                $compact,
                $term
            )
            ||
            str_contains(
                $leet,
                $term
            )
        ) {

            $hasPrivilegedTerm = true;
            break;
        }
    }


    if (
        $hasBrandTerm
        &&
        $hasPrivilegedTerm
    ) {

        return [
            'allowed' => false,
            'reason' =>
                'That username could be mistaken for an official Llama Scout account. Please choose another username.',
            'code' => 'impersonation',
        ];
    }


    /*
     * Block strong inappropriate fragments anywhere in the username.
     */

    foreach (
        username_policy_blocked_terms()
        as $term
    ) {

        if (
            str_contains(
                $compact,
                $term
            )
            ||
            str_contains(
                $leet,
                $term
            )
        ) {

            return [
                'allowed' => false,
                'reason' =>
                    'That username is not available. Please choose another username.',
                'code' => 'inappropriate',
            ];
        }
    }


    /*
     * Block explicit uses of ambiguous terms while allowing a single
     * occurrence inside an ordinary larger word.
     */

    foreach (
        username_policy_ambiguous_terms()
        as $term
    ) {

        if (
            username_policy_contains_ambiguous_term(
                $username,
                $term
            )
        ) {

            return [
                'allowed' => false,
                'reason' =>
                    'That username is not available. Please choose another username.',
                'code' => 'inappropriate',
            ];
        }
    }


    /*
     * A deliberately concatenated username may hide the boundaries above.
     * Two or more ambiguous hits are blocked even when letters touch.
     */

    if (
        username_policy_ambiguous_hit_count(
            $username
        ) >= 2
    ) {

        return [
            'allowed' => false,
            'reason' =>
                'That username is not available. Please choose another username.',
            'code' => 'inappropriate',
        ];
    }


    return [
        'allowed' => true,
        'reason' => '',
        'code' => 'allowed',
    ];
}


function username_is_allowed(
    string $username
): bool {

    $result =
        username_policy_check(
            $username
        );

    return
        (bool)
        $result['allowed'];
}


function username_policy_error(
    string $username
): string {

    $result =
        username_policy_check(
            $username
        );

    return
        (string)
        $result['reason'];
}
