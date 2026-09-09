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

namespace core_ai\aiactions;

use core_ai\aiactions\responses\response_base;

/**
 * Base Action class.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var int Timestamp the action object was created. */
    protected readonly int $timecreated;

    /**
     * Constructor for the class.
     *
     * @param int $contextid The context ID where the action was created.
     */
    public function __construct(
        /** @var int The context ID the action was created in */
        protected int $contextid,
    ) {
        $this->timecreated = \core\di::get(\core\clock::class)->time();
    }

    /**
     * Responsible for storing any action specific data in the database.
     *
     * @param response_base $response The response object to store.
     * @return int The id of the stored action.
     */
    abstract public function store(response_base $response): int;

    /**
     * Get the basename of the class.
     *
     * This is used to generate the action name and description.
     *
     * @return string The basename of the class.
     */
    public static function get_basename(): string {
        return basename(str_replace('\\', '/', static::class));
    }

    /**
     * Get the component that owns this action's language strings.
     *
     * @return string The action's component name.
     */
    public static function get_language_component(): string {
        return \core\component::get_component_from_classname(static::class) ?? 'core_ai';
    }

    /**
     * Get the language string identifier used to resolve the action's help text.
     *
     * @return string The action's help string identifier.
     */
    public static function get_help_string_id(): string {
        return 'action_' . static::get_basename();
    }

    /**
     * Get the action name.
     *
     * Defaults to the action name string resolved against the action's own
     * language component. Core actions resolve to `core_ai`; third-party
     * actions resolve to their plugin component, so custom actions should
     * define `action_<basename>` in their language file.
     *
     * @return string
     */
    public static function get_name(): string {
        $component = static::get_language_component();
        return get_string('action_' . static::get_basename(), $component);
    }

    /**
     * Get the display name for a stored action class.
     *
     * Historical records may reference action classes from plugins that are no longer installed.
     *
     * @param string $actionclass The stored action class name.
     * @return string The action display name, or the stored value when the class is unavailable.
     */
    public static function get_name_for_class(string $actionclass): string {
        if (!class_exists($actionclass) || !is_a($actionclass, self::class, true)) {
            return $actionclass;
        }

        return $actionclass::get_name();
    }

    /**
     * Get the action description.
     *
     * Defaults to the action description string resolved against the action's own
     * language component. Custom actions should define
     * `action_<basename>_desc` in their language file.
     *
     * @return string
     */
    public static function get_description(): string {
        $component = static::get_language_component();
        return get_string('action_' . static::get_basename() . '_desc', $component);
    }

    /**
     * Get the system instruction for the action.
     *
     * Resolved against the action's own language component. Custom actions should
     * define `action_<basename>_instruction` in their language file.
     *
     * @return string The system instruction for the action, or an empty string.
     */
    public static function get_system_instruction(): string {
        $component = static::get_language_component();
        $stringid = 'action_' . static::get_basename() . '_instruction';

        // If the string doesn't exist, return an empty string.
        if (!get_string_manager()->string_exists($stringid, $component)) {
            return '';
        }

        return get_string($stringid, $component);
    }

    /**
     * Get a configuration option.
     *
     * @param string $name The name of the configuration option to get.
     * @return mixed The value of the configuration option.
     */
    public function get_configuration(string $name): mixed {
        return $this->$name;
    }

    /**
     * Return the correct table name for the action.
     *
     * @return string The correct table name for the action.
     */
    protected function get_tablename(): string {
        // Table name should always be in this format.
        return 'ai_action_' . static::get_basename();
    }

    /**
     * Get the class name of the response object.
     *
     * @return string The class name of the response object.
     */
    public static function get_response_classname(): string {
        return responses::class . '\\response_' . static::get_basename();
    }
}
