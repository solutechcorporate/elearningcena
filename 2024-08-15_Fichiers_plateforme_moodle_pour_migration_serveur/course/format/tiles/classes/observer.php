<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event observers supported by this format.
 * @package    format_tiles
 * @copyright  2018 David Watson {@link http://evolutioncode.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_tiles;

/**
 * Event observers supported by this format.
 * @package    format_tiles
 * @copyright  2018 David Watson {@link http://evolutioncode.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Observer for the event course_content_deleted.
     * Deletes the user preference entries for the given course upon course deletion.
     * @param \core\event\course_deleted $event
     * @throws \dml_exception
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $courseid = $event->objectid;
        $DB->delete_records("user_preferences", ["name" => 'format_tiles_stopjsnav_' . $courseid]);
        \format_tiles\local\tile_photo::delete_files_from_ids($courseid);
        \format_tiles\local\format_option::unset_all_course($courseid);
        self::clear_cache_modal_cmids($courseid);
    }

    /**
     * When a section is deleted, delete its tile photo if it has one.
     * @param \core\event\course_section_deleted $event
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event) {
        \format_tiles\local\tile_photo::delete_files_from_ids($event->courseid, $event->objectid);
        \format_tiles\local\format_option::unset_multiple_types(
            $event->courseid,
            $event->objectid,
            [\format_tiles\local\format_option::OPTION_SECTION_PHOTO, \format_tiles\local\format_option::OPTION_SECTION_ICON]
        );
    }

    /**
     * When a course module is deleted, invalidate modalcmids cache for course.
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        if (self::mod_uses_cm_modal_cache($event->other['modulename'])) {
            self::clear_cache_modal_cmids($event->courseid);
        }

    }

    /**
     * When a course module is added, invalidate modalcmids cache for course.
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        if (self::mod_uses_cm_modal_cache($event->other['modulename'])) {
            self::clear_cache_modal_cmids($event->courseid);
        }
    }

    /**
     * When a course module is updated, invalidate modalcmids cache for course.
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        if (self::mod_uses_cm_modal_cache($event->other['modulename'])) {
            self::clear_cache_modal_cmids($event->courseid);
        }
    }

    /**
     * When a course backup is created, the process creates temporary legacy course format options.
     * @param \core\event\course_backup_created $event
     */
    public static function course_backup_created(\core\event\course_backup_created $event) {
        global $DB;
        if ($event->other['type'] == 'course') {
            $istilescourse = $DB->record_exists('course', ['id' => $event->objectid, 'format' => 'tiles']);
            if ($istilescourse) {
                \format_tiles\local\format_option::delete_legacy_format_options($event->objectid);
            }
        }
    }

    /**
     * When a course is restored, the existing course content may be selected to be deleted.
     * @param \core\event\course_restored $event
     * @return void
     */
    public static function course_restored(\core\event\course_restored $event) {
        self::clear_cache_modal_cmids($event->courseid);
    }

    /**
     * Is this module one which uses the cache to store modal cm data?
     * @param string $modname
     * @return bool
     */
    private static function mod_uses_cm_modal_cache(string $modname): bool {
        return in_array($modname, ['resource', 'page', 'url']);
    }

    /**
     * Clear the cache of resource modal IDs for a given course.
     * @param int $courseid
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function clear_cache_modal_cmids(int $courseid) {
        $cache = \cache::make('format_tiles', 'modalcmids');
        $cache->delete($courseid);
    }
}