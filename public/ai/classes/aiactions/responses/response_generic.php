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

namespace core_ai\aiactions\responses;

/**
 * Generic response used when an action has no action-specific response class.
 *
 * @package    core_ai
 * @copyright  2026 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_generic extends response_base {
    /** @var array The response data. */
    private array $responsedata = [];

    /**
     * Constructor.
     *
     * @param bool $success The success status of the action.
     * @param string $actionname Fully-qualified action class name.
     * @param int $errorcode Error code. Must exist if success is false.
     * @param string $error Error name. Must exist if success is false
     * @param string $errormessage Error message.
     */
    public function __construct(
        bool $success,
        string $actionname,
        int $errorcode = 0,
        string $error = '',
        string $errormessage = '',
    ) {
        parent::__construct(
            success: $success,
            actionname: $actionname,
            errorcode: $errorcode,
            error: $error,
            errormessage: $errormessage,
        );
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->responsedata = $response;
    }

    #[\Override]
    public function get_response_data(): array {
        return $this->responsedata;
    }
}
