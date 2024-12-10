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
 * Filename: Shared/RequestResponder.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Types\ErrorData;
use Mcp\Types\ProgressToken;
use Mcp\Types\ProgressNotification;
use InvalidArgumentException;
use RuntimeException;

/**
 * Handles responding to individual requests.
 */
class RequestResponder {
    private bool $responded = false;

    public function __construct(
        private readonly RequestId $requestId,
        private readonly array $params,
        private readonly mixed $request,
        private readonly BaseSession $session,
    ) {}

    public function respond(mixed $response): void {
        if ($this->responded) {
            throw new \RuntimeException('Request already responded to');
        }
        $this->responded = true;

        $this->session->sendResponse($this->requestId, $response);
    }
}