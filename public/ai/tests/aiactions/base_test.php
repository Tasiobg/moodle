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
use core_ai\aiactions;
use ReflectionClass;

/**
 * Test response_base action methods.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\aiactions\base
 */
final class base_test extends \advanced_testcase {
    /**
     * Reset the string manager so mocked strings from one test do not leak into the next.
     */
    public function tearDown(): void {
        parent::tearDown();

        get_string_manager(true);
    }

    /**
     * Test get_basename.
     */
    public function test_get_basename(): void {
        $basename = aiactions\generate_text::get_basename();
        $this->assertEquals('generate_text', $basename);
    }

    /**
     * Test get_language_component.
     */
    public function test_get_language_component(): void {
        $this->assertEquals('core_ai', aiactions\generate_text::get_language_component());
    }

    /**
     * Test custom actions use their owning plugin's language component.
     */
    public function test_get_language_component_for_custom_action(): void {
        global $CFG;

        require_once($CFG->dirroot . '/ai/tests/fixtures/custom_action.php');

        $this->assertEquals('aiplacement_editor', \aiplacement_editor\aiactions\custom_action::get_language_component());
        $this->assertEquals('action_custom_action', \aiplacement_editor\aiactions\custom_action::get_help_string_id());
    }

    /**
     * Test get_name for a custom action resolves against the owning plugin's language component.
     */
    public function test_get_name_for_custom_action(): void {
        global $CFG;

        require_once($CFG->dirroot . '/ai/tests/fixtures/custom_action.php');

        $stringmanager = $this->get_mocked_string_manager();
        $stringmanager->mock_string('action_custom_action', 'aiplacement_editor', 'A fixture action');

        $this->assertSame('A fixture action', \aiplacement_editor\aiactions\custom_action::get_name());
    }

    /**
     * Test get_description for a custom action resolves against the owning plugin's language component.
     */
    public function test_get_description_for_custom_action(): void {
        global $CFG;

        require_once($CFG->dirroot . '/ai/tests/fixtures/custom_action.php');

        $stringmanager = $this->get_mocked_string_manager();
        $stringmanager->mock_string('action_custom_action_desc', 'aiplacement_editor', 'A fixture action description');

        $this->assertSame('A fixture action description', \aiplacement_editor\aiactions\custom_action::get_description());
    }

    /**
     * Test get_system_instruction for a custom action returns an empty string when the
     * instruction string does not exist in the owning plugin's language component.
     */
    public function test_get_system_instruction_for_custom_action(): void {
        global $CFG;

        require_once($CFG->dirroot . '/ai/tests/fixtures/custom_action.php');

        // The mocked string manager only intercepts get_string(); the string_exists() guard inside
        // get_system_instruction() still reads the real store, where the aiplacement_editor component
        // defines no action_custom_action_instruction, so the empty-string fallback is expected.
        $this->assertSame('', \aiplacement_editor\aiactions\custom_action::get_system_instruction());
    }

    /**
     * Test get_system_instruction for a core action returns the instruction string when it exists.
     */
    public function test_get_system_instruction(): void {
        $this->assertSame(
            get_string('action_generate_text_instruction', 'core_ai'),
            aiactions\generate_text::get_system_instruction()
        );
    }

    /**
     * Test get_help_string_id.
     */
    public function test_get_help_string_id(): void {
        $this->assertEquals('action_generate_text', aiactions\generate_text::get_help_string_id());
    }

    /**
     * Test get_name.
     */
    public function test_get_name(): void {
        $this->assertEquals(
            get_string('action_generate_text', 'core_ai'),
            aiactions\generate_text::get_name()
        );
    }

    /**
     * Test get_name_for_class.
     */
    public function test_get_name_for_class(): void {
        $this->assertEquals(
            get_string('action_generate_text', 'core_ai'),
            base::get_name_for_class(aiactions\generate_text::class),
        );
        $this->assertEquals('legacy_action', base::get_name_for_class('legacy_action'));
        $this->assertEquals(
            'unavailable_plugin\\aiactions\\custom_action',
            base::get_name_for_class('unavailable_plugin\\aiactions\\custom_action'),
        );
        $this->assertEquals(\stdClass::class, base::get_name_for_class(\stdClass::class));
    }

    /**
     * Test get_description.
     */
    public function test_get_description(): void {
        $this->assertEquals(
            get_string('action_generate_text_desc', 'core_ai'),
            aiactions\generate_text::get_description()
        );
    }

    /**
     * Test that every action class implements a constructor.
     */
    public function test_constructor(): void {
        $classes = [];

        $contextid = 1;
        // Create an anonymous class that extends the base class.
        $base = new class($contextid) extends \core_ai\aiactions\base {
            /**
             * Store the response.
             * @param response_base $response
             * @return int
             */
            public function store(response_base $response): int {
                return 0;
            }
        };

        $reflection = new ReflectionClass($base); // Create a ReflectionClass for the anonymous class.

        // Use the location of the base class to get the AI action classes.
        $filepath = $reflection->getParentClass()->getFileName();
        $directory = dirname($filepath);
        $files = scandir($directory);

        foreach ($files as $file) {
            // Match files that are PHP files and not specifically just 'base.*.php'.
            if (str_ends_with($file, '.php')) {
                $classname = str_replace('.php', '', $file);
                $classes[] = 'core_ai\\aiactions\\' . $classname;
            }
        }

        // Ensure that some classes were found.
        $this->assertNotEmpty($classes, 'No classes were found for testing.');

        // For each action class, check that they have a constructor.
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            $this->assertNotNull($constructor, 'Class ' . $class . ' does not have a constructor.');
        }
    }

}
