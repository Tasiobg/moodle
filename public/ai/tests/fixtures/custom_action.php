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

namespace aiplacement_editor\aiactions;

use core_ai\aiactions\base;
use core_ai\aiactions\responses\response_base;

/**
 * Test action used to verify language component discovery.
 *
 * @package   core_ai
 * @copyright 2026 Tasio Bertomeu Gomez <tasio.bertomeu@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_action extends base {
    #[\Override]
    public function store(response_base $response): int {
        return 0;
    }
}
