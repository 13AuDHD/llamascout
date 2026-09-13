<?php

declare(strict_types=1);

/**
 * Llama Scout local SVG icon system.
 *
 * Icons live in /assets/icons and are rendered inline so they inherit the
 * surrounding text color and do not require an external icon font or CDN.
 */

const LLAMA_ICON_DEFAULT_STROKE_WIDTH = 2.0;

/**
 * Render a local SVG icon.
 *
 * Supported options:
 * - class: additional CSS class names
 * - label: accessible label for a meaningful standalone icon
 * - stroke_width: per-icon stroke-width override from 0.5 through 4
 */
function llama_icon(string $name, array $options = []): string
{
    $name = strtolower(trim($name));

    if (
        $name === ''
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name)
    ) {
        return '';
    }

    static $sourceCache = [];
    static $missingLogged = [];

    if (!array_key_exists($name, $sourceCache)) {
        $path = dirname(__DIR__) . '/assets/icons/' . $name . '.svg';

        if (!is_file($path) || !is_readable($path)) {
            $sourceCache[$name] = null;

            if (empty($missingLogged[$name])) {
                error_log('Llama Scout icon not found: ' . $name);
                $missingLogged[$name] = true;
            }
        } else {
            $source = file_get_contents($path);

            if (
                !is_string($source)
                || trim($source) === ''
                || !preg_match('/<svg\b/i', $source)
                || llama_icon_source_is_unsafe($source)
            ) {
                $sourceCache[$name] = null;

                if (empty($missingLogged[$name])) {
                    error_log('Llama Scout icon could not be rendered safely: ' . $name);
                    $missingLogged[$name] = true;
                }
            } else {
                $sourceCache[$name] = trim($source);
            }
        }
    }

    $svg = $sourceCache[$name] ?? null;

    if (!is_string($svg) || $svg === '') {
        return '';
    }

    $classNames = ['llama-icon'];
    $extraClasses = trim((string) ($options['class'] ?? ''));

    if ($extraClasses !== '') {
        foreach (preg_split('/\s+/', $extraClasses) ?: [] as $className) {
            if (
                $className !== ''
                && preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $className)
            ) {
                $classNames[] = $className;
            }
        }
    }

    $classNames = array_values(array_unique($classNames));

    $label = trim((string) ($options['label'] ?? ''));

    $strokeWidth = LLAMA_ICON_DEFAULT_STROKE_WIDTH;

    if (array_key_exists('stroke_width', $options)) {
        $requestedStrokeWidth = (float) $options['stroke_width'];

        if (
            $requestedStrokeWidth >= 0.5
            && $requestedStrokeWidth <= 4.0
        ) {
            $strokeWidth = $requestedStrokeWidth;
        }
    }

    $svg = preg_replace_callback(
        '/<svg\b([^>]*)>/i',
        static function (array $matches) use (
            $classNames,
            $label,
            $strokeWidth
        ): string {
            $attributes = (string) ($matches[1] ?? '');

            foreach (
                [
                    'class',
                    'aria-hidden',
                    'aria-label',
                    'focusable',
                    'role',
                ] as $attribute
            ) {
                $attributes = llama_icon_remove_root_attribute(
                    $attributes,
                    $attribute
                );
            }

            if (preg_match('/\sstroke\s*=\s*(["\'])/i', $attributes)) {
                $attributes = llama_icon_set_root_attribute(
                    $attributes,
                    'stroke-width',
                    llama_icon_format_stroke_width($strokeWidth)
                );
            }

            $attributes = llama_icon_set_root_attribute(
                $attributes,
                'class',
                implode(' ', $classNames)
            );

            $attributes = llama_icon_set_root_attribute(
                $attributes,
                'focusable',
                'false'
            );

            if ($label === '') {
                $attributes = llama_icon_set_root_attribute(
                    $attributes,
                    'aria-hidden',
                    'true'
                );
            } else {
                $attributes = llama_icon_set_root_attribute(
                    $attributes,
                    'role',
                    'img'
                );

                $attributes = llama_icon_set_root_attribute(
                    $attributes,
                    'aria-label',
                    $label
                );
            }

            return '<svg' . $attributes . '>';
        },
        $svg,
        1
    ) ?? $svg;

    return $svg;
}

function llama_icon_source_is_unsafe(string $source): bool
{
    $unsafePatterns = [
        '/<script\b/i',
        '/<foreignObject\b/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
        '/<!ENTITY\b/i',
        '/<\?xml-stylesheet\b/i',
    ];

    foreach ($unsafePatterns as $pattern) {
        if (preg_match($pattern, $source)) {
            return true;
        }
    }

    return false;
}

function llama_icon_remove_root_attribute(
    string $attributes,
    string $attribute
): string {
    $pattern =
        '/\s+'
        . preg_quote($attribute, '/')
        . '\s*=\s*(["\']).*?\1/i';

    return preg_replace($pattern, '', $attributes) ?? $attributes;
}

function llama_icon_set_root_attribute(
    string $attributes,
    string $attribute,
    string $value
): string {
    $escapedValue = htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $pattern =
        '/\s+'
        . preg_quote($attribute, '/')
        . '\s*=\s*(["\']).*?\1/i';

    if (preg_match($pattern, $attributes)) {
        return preg_replace(
            $pattern,
            ' ' . $attribute . '="' . $escapedValue . '"',
            $attributes,
            1
        ) ?? $attributes;
    }

    return $attributes
        . ' '
        . $attribute
        . '="'
        . $escapedValue
        . '"';
}

function llama_icon_format_stroke_width(float $strokeWidth): string
{
    $formatted = number_format($strokeWidth, 2, '.', '');

    return rtrim(rtrim($formatted, '0'), '.');
}
