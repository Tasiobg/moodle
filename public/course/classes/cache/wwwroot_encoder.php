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

use stdClass;

/**
 * Swaps the site wwwroot for a domain-agnostic placeholder in the coursemodinfo cache.
 *
 * Activity modules build absolute URLs while populating {@see \cached_cm_info} (popup onclick
 * handlers, pluginfile.php links inside activity descriptions, icon URLs). Storing them verbatim
 * bakes the writing domain into content that is then served on whichever domain answers the request.
 *
 * Encoding happens once when the cache entry is written and decoding when {@see \core_course\cm_info}
 * and {@see \section_info} objects are constructed from it, so module plugins keep returning
 * absolute URLs and require no changes.
 *
 * @package    core_course
 * @copyright  2026 Tasio Bertomeu Gomez <tasio.bertomeu@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wwwroot_encoder {
    /** @var string Token stored in the cache in place of the site wwwroot. */
    public const PLACEHOLDER = '@@COURSECACHEWWWROOT@@';

    /** @var string[] Cached course module fields that may hold absolute site URLs. */
    private const CM_FIELDS = ['content', 'extra', 'onclick', 'iconurl', 'customdata'];

    /** @var string[] Cached course section fields that may hold absolute site URLs. */
    private const SECTION_FIELDS = ['summary'];

    /** @var string[] Cached fields whose value may be a PHP serialized blob. */
    private const SERIALIZED_FIELDS = ['customdata'];

    /**
     * @var string Characters that keep a host or path segment going, so a wwwroot followed by one
     * of them is only a prefix of an unrelated URL and must not be encoded.
     */
    private const SEGMENT_CHARS = 'A-Za-z0-9\-._~%';

    /**
     * Replaces the site wwwroot with the placeholder in every cached field that may hold a URL.
     *
     * Encoding is idempotent, so it is safe to run over an entry that partial rebuilds have
     * carried over from a previous cache generation.
     *
     * @param stdClass $coursemodinfo the object about to be written to the coursemodinfo cache
     */
    public static function encode_course_cache(stdClass $coursemodinfo): void {
        foreach ($coursemodinfo->modinfo as $cmid => $mod) {
            $coursemodinfo->modinfo[$cmid] = self::replace_fields($mod, self::CM_FIELDS, self::encode(...));
        }
        foreach ($coursemodinfo->sectioncache as $sectionid => $section) {
            $coursemodinfo->sectioncache[$sectionid] = self::replace_fields(
                $section,
                self::SECTION_FIELDS,
                self::encode(...),
            );
        }
    }

    /**
     * Returns a copy of a cached course module entry with its URLs pointing at the current wwwroot.
     *
     * Needed by code reading the cache entry directly instead of through {@see \core_course\cm_info}.
     *
     * @param stdClass $mod a single entry of the cached modinfo array
     * @return stdClass
     */
    public static function decode_course_module(stdClass $mod): stdClass {
        return self::replace_fields($mod, self::CM_FIELDS, self::decode(...));
    }

    /**
     * Returns a copy of a cached course section entry with its URLs pointing at the current wwwroot.
     *
     * Needed by code reading the cache entry directly instead of through {@see \section_info}.
     *
     * @param stdClass $section a single entry of the cached sectioncache array
     * @return stdClass
     */
    public static function decode_course_section(stdClass $section): stdClass {
        return self::replace_fields($section, self::SECTION_FIELDS, self::decode(...));
    }

    /**
     * Replaces the site wwwroot with the placeholder.
     *
     * @param mixed $value scalar, array or stdClass to encode; anything else is returned untouched
     * @param bool $skipserialized leave PHP serialized strings alone, see {@see self::is_serialized()}
     * @return mixed the encoded value
     */
    public static function encode(mixed $value, bool $skipserialized = false): mixed {
        global $CFG;

        $pattern = '/' . preg_quote($CFG->wwwroot, '/') . '(?![' . self::SEGMENT_CHARS . '])/';

        return self::walk(
            $value,
            fn(string $string): string => preg_replace($pattern, self::PLACEHOLDER, $string) ?? $string,
            $skipserialized,
        );
    }

    /**
     * Replaces the placeholder with the wwwroot of the domain serving the current request.
     *
     * Values cached before this encoding existed contain no placeholder and are returned unchanged.
     *
     * @param mixed $value scalar, array or stdClass to decode; anything else is returned untouched
     * @param bool $skipserialized leave PHP serialized strings alone, see {@see self::is_serialized()}
     * @return mixed the decoded value
     */
    public static function decode(mixed $value, bool $skipserialized = false): mixed {
        global $CFG;

        return self::walk(
            $value,
            fn(string $string): string => str_contains($string, self::PLACEHOLDER)
                ? str_replace(self::PLACEHOLDER, $CFG->wwwroot, $string)
                : $string,
            $skipserialized,
        );
    }

    /**
     * Returns a copy of a cached entry with the listed fields run through a replacer.
     *
     * @param stdClass $data cached course module or section entry
     * @param string[] $fields names of the fields to process
     * @param callable $replacer either {@see self::encode()} or {@see self::decode()}
     * @return stdClass
     */
    private static function replace_fields(stdClass $data, array $fields, callable $replacer): stdClass {
        // Clone so entries reused from the previous cache generation are never modified in place.
        $data = clone $data;
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                $data->$field = $replacer($data->$field, in_array($field, self::SERIALIZED_FIELDS, true));
            }
        }
        return $data;
    }

    /**
     * Recursively applies a string replacer to every string held by a value.
     *
     * @param mixed $value value to walk
     * @param callable $replacer takes a string and returns the rewritten string
     * @param bool $skipserialized leave PHP serialized strings alone
     * @return mixed
     */
    private static function walk(mixed $value, callable $replacer, bool $skipserialized): mixed {
        if (is_string($value)) {
            return ($skipserialized && self::is_serialized($value)) ? $value : $replacer($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $walked = self::walk($item, $replacer, $skipserialized);
                // Writing back only when something changed keeps untouched arrays from being copied.
                if ($walked !== $item) {
                    $value[$key] = $walked;
                }
            }
            return $value;
        }

        // Only stdClass is traversed: other objects may rely on state that a blind rewrite would corrupt.
        if ($value instanceof stdClass) {
            $value = clone $value;
            foreach (get_object_vars($value) as $key => $item) {
                $value->$key = self::walk($item, $replacer, $skipserialized);
            }
            return $value;
        }

        return $value;
    }

    /**
     * Checks whether a string holds PHP serialized data.
     *
     * Rewriting inside one would leave its length prefixes wrong and make it impossible to
     * unserialize, so such values are skipped. Plugins that serialize URLs into customdata
     * (as mod_resource does with its display options) therefore keep the behaviour they had
     * before this encoding existed instead of getting back corrupted data.
     *
     * This is a prefix heuristic, so it only runs over the fields that can hold a serialized blob
     * {@see self::SERIALIZED_FIELDS}.
     *
     * @param string $value
     * @return bool
     */
    private static function is_serialized(string $value): bool {
        return (bool) preg_match('/^(?:N;|[bid]:[^;]*;|[aCOSs]:\d+:|E:\d+:)/', $value);
    }
}
