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
 * Filename: Shared/RequestContext.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\RequestId;

/**
 * Request context holding the current request's information.
 */
class RequestContext {
    public function __construct(
        private readonly RequestId $requestId,
        private readonly array $params,
        private readonly BaseSession $session,
    ) {}

    public function getRequestId(): RequestId {
        return $this->requestId;
    }

    public function getParams(): array {
        return $this->params;
    }

    public function getSession(): BaseSession {
        return $this->session;
    }
}