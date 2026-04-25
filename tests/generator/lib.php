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
 * Data generator for mod_playergroup tests.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator class for mod_playergroup.
 *
 * Extends the base module generator so that
 * $this->getDataGenerator()->create_module('playergroup', [...]) works
 * in PHPUnit tests. Defaults cover all NOT NULL columns.
 */
class mod_playergroup_generator extends testing_module_generator {
    /**
     * Creates a new mod_playergroup activity instance for testing.
     *
     * @param array|stdClass|null $record Optional field overrides.
     * @param array|null $options Optional generator options passed to parent.
     * @return stdClass The created course-module record.
     */
    public function create_instance($record = null, ?array $options = null): stdClass {
        $record = (object) (array) $record;

        // Apply defaults for all required fields.
        $record->minmembers          = $record->minmembers ?? 2;
        $record->maxmembers          = $record->maxmembers ?? 5;
        $record->canleave            = $record->canleave ?? 1;
        $record->deletegroups        = $record->deletegroups ?? 0;
        $record->grade               = $record->grade ?? 0;
        $record->completionjoingroup = $record->completionjoingroup ?? 0;
        $record->timeopen            = $record->timeopen ?? 0;
        $record->timeclose           = $record->timeclose ?? 0;
        $record->groupingmode        = $record->groupingmode ?? 'none';

        return parent::create_instance($record, $options);
    }
}
