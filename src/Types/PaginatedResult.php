<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2024 Logiscape LLC <https://logiscape.com>
 *
 * Based on the Python SDK for the Model Context Protocol
 * https://github.com/modelcontextprotocol/python-sdk
 *
 * PHP conversion developed by:
 * - Josh Abbott
 * - Claude 3.5 Sonnet (Anthropic AI model)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    logiscape/mcp-sdk-php
 * @author     Josh Abbott <https://joshabbott.com>
 * @copyright  Logiscape LLC
 * @license    MIT License
 * @link       https://github.com/logiscape/mcp-sdk-php
 *
 * Filename: Types/PaginatedResult.php
 */

declare(strict_types=1);

namespace Mcp\Types;

class PaginatedResult extends Result {
    public function __construct(
        public ?string $nextCursor = null,
        ?Meta $_meta = null,
    ) {
        parent::__construct($_meta);
    }

    public function validate(): void {
        parent::validate();
        // no extra validation needed for nextCursor
    }

    public function jsonSerialize(): mixed {
        $data = parent::jsonSerialize();
        if ($this->nextCursor !== null) {
            $data['nextCursor'] = $this->nextCursor;
        }
        return $data;
    }
}