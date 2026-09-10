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

declare(strict_types=1);

namespace core_course\cache;

use core_cache\cache;
use core_course\modinfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use stdClass;

/**
 * Tests for the coursemodinfo cache wwwroot encoder.
 *
 * @package    core_course
 * @category   test
 * @copyright  2026 Tasio Bertomeu Gomez <tasio.bertomeu@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(wwwroot_encoder::class)]
final class wwwroot_encoder_test extends \advanced_testcase {
    public function test_encode_and_decode_string(): void {
        global $CFG;

        $encoded = wwwroot_encoder::encode("window.open('{$CFG->wwwroot}/mod/page/view.php?id=7');");

        $this->assertStringNotContainsString($CFG->wwwroot, $encoded);
        $this->assertStringContainsString(wwwroot_encoder::PLACEHOLDER, $encoded);
        $this->assertSame(
            "window.open('{$CFG->wwwroot}/mod/page/view.php?id=7');",
            wwwroot_encoder::decode($encoded),
        );
    }

    public function test_decode_uses_the_wwwroot_of_the_current_request(): void {
        global $CFG;

        $encoded = wwwroot_encoder::encode("{$CFG->wwwroot}/pluginfile.php/1/mod_label/intro/logo.png");

        $CFG->wwwroot = 'https://other.example.com';

        $this->assertSame(
            'https://other.example.com/pluginfile.php/1/mod_label/intro/logo.png',
            wwwroot_encoder::decode($encoded),
        );
    }

    public function test_encode_and_decode_nested_values(): void {
        global $CFG;

        $value = (object) [
            'url' => "{$CFG->wwwroot}/mod/url/view.php?id=3",
            'nested' => ['deep' => (object) ['url' => "{$CFG->wwwroot}/pluginfile.php/1/x.png"]],
            'count' => 42,
            'enabled' => true,
            'nothing' => null,
        ];

        $encoded = wwwroot_encoder::encode($value);

        $this->assertStringContainsString(wwwroot_encoder::PLACEHOLDER, $encoded->url);
        $this->assertStringContainsString(wwwroot_encoder::PLACEHOLDER, $encoded->nested['deep']->url);
        $this->assertSame(42, $encoded->count);
        $this->assertTrue($encoded->enabled);
        $this->assertNull($encoded->nothing);
        $this->assertEquals($value, wwwroot_encoder::decode($encoded));
    }

    public function test_encode_does_not_modify_the_original_value(): void {
        global $CFG;

        $value = (object) ['url' => "{$CFG->wwwroot}/mod/url/view.php?id=3"];

        wwwroot_encoder::encode($value);

        $this->assertSame("{$CFG->wwwroot}/mod/url/view.php?id=3", $value->url);
    }

    /**
     * Serialized payloads carry byte length prefixes, so they must be left exactly as they are.
     */
    public function test_encode_leaves_serialized_strings_untouched(): void {
        global $CFG;

        $serialized = serialize(['link' => "{$CFG->wwwroot}/pluginfile.php/1/x.png"]);

        $encoded = wwwroot_encoder::encode($serialized, skipserialized: true);

        $this->assertSame($serialized, $encoded);
        $this->assertSame($serialized, wwwroot_encoder::decode($encoded, skipserialized: true));
        $this->assertIsArray(unserialize($encoded));
    }

    /**
     * The serialized check is a prefix heuristic, so it only runs over the fields that can hold a
     * serialized blob. Prose fields must be encoded even when they happen to start like one.
     */
    public function test_encode_course_cache_encodes_prose_that_looks_serialized(): void {
        global $CFG;

        $serialized = serialize(['link' => "{$CFG->wwwroot}/pluginfile.php/1/x.png"]);
        $coursemodinfo = (object) [
            'modinfo' => [
                7 => (object) [
                    'content' => "i: love this; see {$CFG->wwwroot}/mod/page/view.php?id=7",
                    'customdata' => $serialized,
                ],
            ],
            'sectioncache' => [
                3 => (object) ['summary' => "b: see {$CFG->wwwroot}/course/view.php?id=1"],
            ],
        ];

        wwwroot_encoder::encode_course_cache($coursemodinfo);

        $this->assertSame(
            'i: love this; see ' . wwwroot_encoder::PLACEHOLDER . '/mod/page/view.php?id=7',
            $coursemodinfo->modinfo[7]->content,
        );
        $this->assertSame(
            'b: see ' . wwwroot_encoder::PLACEHOLDER . '/course/view.php?id=1',
            $coursemodinfo->sectioncache[3]->summary,
        );
        $this->assertSame($serialized, $coursemodinfo->modinfo[7]->customdata);
    }

    public function test_encode_is_idempotent(): void {
        global $CFG;

        $encoded = wwwroot_encoder::encode("{$CFG->wwwroot}/mod/page/view.php?id=7");

        $this->assertSame($encoded, wwwroot_encoder::encode($encoded));
    }

    /**
     * A wwwroot that happens to be the prefix of an unrelated address is not a match, otherwise
     * decoding on another domain would rewrite the middle of a URL that was never ours.
     *
     * @param string $wwwroot
     * @param string $url
     */
    #[DataProvider('unrelated_url_provider')]
    public function test_encode_only_matches_whole_url_segments(string $wwwroot, string $url): void {
        global $CFG;

        $CFG->wwwroot = $wwwroot;

        $this->assertSame($url, wwwroot_encoder::encode($url));
    }

    /**
     * Data provider for {@see test_encode_only_matches_whole_url_segments()}.
     *
     * @return array[]
     */
    public static function unrelated_url_provider(): array {
        return [
            'longer path segment' => [
                'https://moodle.example.com/tenanta',
                'https://moodle.example.com/tenantav2-archive/info.php',
            ],
            'longer host' => [
                'https://example.com',
                'https://example.com.attacker.test/info.php',
            ],
            'host with a dash' => [
                'https://example.com',
                'https://example.com-archive/info.php',
            ],
        ];
    }

    /**
     * The wwwroot is encoded whenever the segment it ends with actually ends there.
     *
     * @param string $suffix
     */
    #[TestWith(['/mod/page/view.php?id=7'])]
    #[TestWith(['?redirect=0'])]
    #[TestWith(['#section-1'])]
    #[TestWith([''])]
    #[TestWith(["' target='_blank'"])]
    public function test_encode_matches_the_wwwroot_at_a_segment_boundary(string $suffix): void {
        global $CFG;

        $this->assertSame(
            wwwroot_encoder::PLACEHOLDER . $suffix,
            wwwroot_encoder::encode("{$CFG->wwwroot}{$suffix}"),
        );
    }

    public function test_decode_course_module_only_touches_url_bearing_fields(): void {
        global $CFG;

        $mod = (object) [
            'content' => "<img src='" . wwwroot_encoder::PLACEHOLDER . "/pluginfile.php/1/x.png'>",
            'customdata' => ['link' => wwwroot_encoder::PLACEHOLDER . '/mod/page/view.php?id=7'],
            'name' => 'Page ' . wwwroot_encoder::PLACEHOLDER,
        ];

        $decoded = wwwroot_encoder::decode_course_module($mod);

        $this->assertSame("<img src='{$CFG->wwwroot}/pluginfile.php/1/x.png'>", $decoded->content);
        $this->assertSame("{$CFG->wwwroot}/mod/page/view.php?id=7", $decoded->customdata['link']);
        $this->assertSame('Page ' . wwwroot_encoder::PLACEHOLDER, $decoded->name);
        // The cached entry itself must not be altered.
        $this->assertStringContainsString(wwwroot_encoder::PLACEHOLDER, $mod->content);
    }

    public function test_decode_course_section_only_touches_url_bearing_fields(): void {
        global $CFG;

        $section = (object) [
            'summary' => "<a href='" . wwwroot_encoder::PLACEHOLDER . "/course/view.php?id=1'>Home</a>",
            'name' => 'Week ' . wwwroot_encoder::PLACEHOLDER,
        ];

        $decoded = wwwroot_encoder::decode_course_section($section);

        $this->assertSame("<a href='{$CFG->wwwroot}/course/view.php?id=1'>Home</a>", $decoded->summary);
        $this->assertSame('Week ' . wwwroot_encoder::PLACEHOLDER, $decoded->name);
        $this->assertStringContainsString(wwwroot_encoder::PLACEHOLDER, $section->summary);
    }

    /**
     * get_array_of_activities() is public and bypasses cm_info, so it must never hand out the
     * raw placeholder.
     */
    public function test_get_array_of_activities_returns_decoded_urls(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/resourcelib.php');

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'popupwidth' => 620,
            'popupheight' => 450,
        ]);

        rebuild_course_cache((int) $course->id);

        $mods = modinfo::get_array_of_activities(get_course($course->id), true);

        $onclicks = array_filter(array_map(fn(stdClass $mod) => $mod->onclick ?? '', $mods));
        $this->assertNotEmpty($onclicks, 'The page module should have cached a popup onclick handler');
        foreach ($onclicks as $onclick) {
            $this->assertStringNotContainsString(wwwroot_encoder::PLACEHOLDER, $onclick);
            $this->assertStringContainsString($CFG->wwwroot, $onclick);
        }
    }

    public function test_decode_leaves_entries_cached_before_this_encoding_existed_alone(): void {
        global $CFG;

        $legacy = "{$CFG->wwwroot}/mod/page/view.php?id=7";

        $this->assertSame($legacy, wwwroot_encoder::decode($legacy));
    }

    public function test_encode_course_cache_only_touches_url_bearing_fields(): void {
        global $CFG;

        $coursemodinfo = (object) [
            'modinfo' => [
                7 => (object) [
                    'content' => "<img src='{$CFG->wwwroot}/pluginfile.php/1/x.png'>",
                    'onclick' => "window.open('{$CFG->wwwroot}/mod/page/view.php?id=7');",
                    'iconurl' => "{$CFG->wwwroot}/theme/image.php/boost/mod_page/1/icon",
                    'customdata' => ['link' => "{$CFG->wwwroot}/mod/page/view.php?id=7"],
                    'extra' => "{$CFG->wwwroot}/legacy",
                    'name' => "Page about {$CFG->wwwroot}",
                    'extraclasses' => 'highlight',
                ],
            ],
            'sectioncache' => [
                3 => (object) [
                    'summary' => "<a href='{$CFG->wwwroot}/course/view.php?id=1'>Home</a>",
                    'availability' => "{$CFG->wwwroot}",
                ],
            ],
        ];

        wwwroot_encoder::encode_course_cache($coursemodinfo);

        $mod = $coursemodinfo->modinfo[7];
        foreach (['content', 'onclick', 'iconurl', 'extra'] as $field) {
            $this->assertStringNotContainsString($CFG->wwwroot, $mod->$field, "Field {$field} was not encoded");
        }
        $this->assertStringNotContainsString($CFG->wwwroot, $mod->customdata['link']);
        $this->assertStringNotContainsString($CFG->wwwroot, $coursemodinfo->sectioncache[3]->summary);

        // Fields that never hold URLs are left as they are so placeholders can never leak to a user.
        $this->assertSame("Page about {$CFG->wwwroot}", $mod->name);
        $this->assertSame('highlight', $mod->extraclasses);
        $this->assertSame($CFG->wwwroot, $coursemodinfo->sectioncache[3]->availability);
    }

    /**
     * The cached entry must be free of the building domain, and a request arriving on another
     * domain must get its own wwwroot back.
     */
    public function test_course_cache_is_stored_without_the_building_domain(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/resourcelib.php');

        $course = $this->getDataGenerator()->create_course(['numsections' => 1], ['createsections' => true]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'popupwidth' => 620,
            'popupheight' => 450,
        ]);

        rebuild_course_cache((int) $course->id);

        $cached = cache::make('core', 'coursemodinfo')->get_versioned(
            $course->id,
            (int) get_course($course->id)->cacherev,
        );
        $onclicks = array_filter(array_map(fn(stdClass $mod) => $mod->onclick ?? '', $cached->modinfo));
        $this->assertNotEmpty($onclicks, 'The page module should have cached a popup onclick handler');
        foreach ($onclicks as $onclick) {
            $this->assertStringNotContainsString($CFG->wwwroot, $onclick);
            $this->assertStringContainsString(wwwroot_encoder::PLACEHOLDER, $onclick);
        }

        // Serve the very same cache entry to a request arriving on a different domain.
        $CFG->wwwroot = 'https://other.example.com';
        modinfo::clear_instance_cache();

        foreach (get_fast_modinfo($course->id)->get_cms() as $cm) {
            $this->assertStringContainsString('https://other.example.com', $cm->onclick);
            $this->assertStringNotContainsString(wwwroot_encoder::PLACEHOLDER, $cm->onclick);
        }
    }

    public function test_section_summary_is_stored_without_the_building_domain(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['numsections' => 1], ['createsections' => true]);
        $summary = "<a href='{$CFG->wwwroot}/course/view.php?id={$course->id}'>Home</a>";
        $DB->set_field('course_sections', 'summary', $summary, ['course' => $course->id, 'section' => 1]);

        rebuild_course_cache((int) $course->id);

        $cached = cache::make('core', 'coursemodinfo')->get_versioned(
            $course->id,
            (int) get_course($course->id)->cacherev,
        );
        $cachedsummaries = array_column((array) $cached->sectioncache, 'summary');
        $this->assertContains(
            "<a href='" . wwwroot_encoder::PLACEHOLDER . "/course/view.php?id={$course->id}'>Home</a>",
            $cachedsummaries,
        );

        $CFG->wwwroot = 'https://other.example.com';
        modinfo::clear_instance_cache();

        $this->assertSame(
            "<a href='https://other.example.com/course/view.php?id={$course->id}'>Home</a>",
            get_fast_modinfo($course->id)->get_section_info(1)->summary,
        );
    }

    /**
     * mod_resource serializes its display options into customdata, so the round trip must keep
     * them unserializable.
     */
    public function test_serialized_customdata_survives_the_cache_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('resource', [
            'course' => $course->id,
            'showsize' => 1,
            'showtype' => 1,
        ]);

        rebuild_course_cache((int) $course->id);
        modinfo::clear_instance_cache();

        $customdata = (array) get_fast_modinfo($course->id)->get_cm($resource->cmid)->customdata;

        $this->assertArrayHasKey('displayoptions', $customdata);
        $this->assertIsArray(unserialize_array($customdata['displayoptions']));
    }
}
