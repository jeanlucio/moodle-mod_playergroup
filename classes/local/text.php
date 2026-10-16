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
 * Turns a stored name into plain text.
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
}
