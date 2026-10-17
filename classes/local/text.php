<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Text helpers for names that are shown in more than one kind of output.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Turns stored names and descriptions into plain text.
 */
final class text {
    /**
     * Returns a stored name as plain text, ready for a destination that escapes it once.
     *
     * The string filters (multilang, ...) are applied, then markup is stripped and entities are
     * decoded. That is what a double-mustache variable, an attribute, a CSV cell, a file name, a
     * plain-text message or the app's text interpolation need: they escape the text themselves, so
     * handing them the result of format_string() shows "P&amp;D" for "P&D".
     *
     * format_string() alone cannot do this: its result is HTML, and with the site setting
     * "formatstringstriptags" off it goes through the HTML cleaner and carries "&amp;" even when
     * asked not to escape.
     *
     * @param string|null $string The stored text.
     * @param \context|null $context Context for the filters; defaults to the page context.
     * @return string Plain text, unescaped.
     */
    public static function plain(?string $string, ?\context $context = null): string {
        if ($string === null || $string === '') {
            return '';
        }

        $options = $context === null ? [] : ['context' => $context];
        $formatted = format_string($string, true, $options);

        return html_entity_decode(strip_tags($formatted), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Cleans a group description typed in a plain text field.
     *
     * The students write it in a plain textarea, so it is kept as typed (FORMAT_PLAIN) and only the
     * markup is removed. Stored as HTML it would lose its line breaks on the page and come back to the
     * editing field with "&amp;" for "&".
     *
     * @param string $description The submitted text.
     * @return string The text without markup, with Unix line breaks.
     */
    public static function clean_description(string $description): string {
        $description = str_replace(["\r\n", "\r"], "\n", $description);

        return trim(clean_param($description, PARAM_TEXT));
    }

    /**
     * Returns a stored group description as the plain text the editing field must show.
     *
     * Descriptions saved by earlier versions are HTML with entities; those saved now are plain text.
     *
     * @param string|null $description The stored description.
     * @param int $format The stored format (FORMAT_*).
     * @return string Plain text, unescaped.
     */
    public static function description_for_editing(?string $description, int $format): string {
        if ($description === null || $description === '') {
            return '';
        }
        if ($format === (int) FORMAT_PLAIN) {
            return $description;
        }

        return trim(html_to_text($description, 0, false));
    }

    /** Longest group name the groups table holds. */
    public const MAX_GROUP_NAME = 254;

    /** Longest badge the plugin table holds. */
    public const MAX_BADGE = 255;

    /**
     * Checks the name and the badge a student submitted for a group, and returns the name to store.
     *
     * The core only trims the name: an empty one creates a group nobody can tell apart, and one longer than
     * the column fails with a database error instead of a message.
     *
     * @param string $name The submitted name.
     * @param string $badge The submitted badge.
     * @return string The name without the spaces around it.
     * @throws \moodle_exception When the name is empty or too long, or the badge is too long.
     */
    public static function require_valid_group_fields(string $name, string $badge): string {
        $name = trim($name);
        $length = \core_text::strlen($name);
        if ($length === 0 || $length > self::MAX_GROUP_NAME) {
            throw new \moodle_exception('errinvalidgroupname', 'mod_playergroup', '', self::MAX_GROUP_NAME);
        }
        if (\core_text::strlen($badge) > self::MAX_BADGE) {
            throw new \moodle_exception('errinvalidbadge', 'mod_playergroup', '', self::MAX_BADGE);
        }

        return $name;
    }
}
