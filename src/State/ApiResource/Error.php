<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\State\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Error as Operation;
use ApiPlatform\Metadata\ErrorResource;
use ApiPlatform\Metadata\Exception\HttpExceptionInterface;
use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface as SymfonyHttpExceptionInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\WebLink\Link;

#[ErrorResource(
    openapi: false,
    uriVariables: ['status'],
    uriTemplate: '/errors/{status}',
    operations: [
        new Operation(
            name: '_api_errors_problem',
            routeName: 'api_errors',
            outputFormats: ['json' => ['application/problem+json']],
            normalizationContext: [
                'groups' => ['jsonproblem'],
                'skip_null_values' => true,
            ],
        ),
        new Operation(
            name: '_api_errors_hydra',
            routeName: 'api_errors',
            outputFormats: ['jsonld' => ['application/problem+json']],
            normalizationContext: [
                'groups' => ['jsonld'],
                'skip_null_values' => true,
            ],
            links: [new Link(rel: 'http://www.w3.org/ns/json-ld#error', href: 'http://www.w3.org/ns/hydra/error')],
        ),
        new Operation(
            name: '_api_errors_jsonapi',
            routeName: 'api_errors',
            outputFormats: ['jsonapi' => ['application/vnd.api+json']],
            normalizationContext: [
                'groups' => ['jsonapi'],
                'skip_null_values' => true,
            ],
        ),
        new Operation(
            name: '_api_errors',
            routeName: 'api_errors'
        ),
    ],
    provider: 'api_platform.state.error_provider',
    graphQlOperations: []
)]
class Error extends \Exception implements ProblemExceptionInterface, HttpExceptionInterface
{
    public function __construct(
        private string $title,
        private string $detail,
        #[ApiProperty(identifier: true)] private int $status,
        ?array $originalTrace = null,
        private ?string $instance = null,
        private string $type = 'about:blank',
        private array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($title, $status, $previous);

        if (!$originalTrace) {
            return;
        }

        $this->originalTrace = [];
        foreach ($originalTrace as $i => $t) {
            unset($t['args']); // we don't want arguments in our JSON traces, especially with xdebug
            $this->originalTrace[$i] = $t;
        }
    }

    #[Groups(['jsonapi'])]
    public function getId(): string
    {
        return (string) $this->status;
    }

    #[SerializedName('trace')]
    #[Groups(['trace'])]
    public ?array $originalTrace = null;

    #[Groups(['jsonld'])]
    public function getDescription(): ?string
    {
        return $this->detail;
    }

    public static function createFromException(\Exception|\Throwable $exception, int $status): self
    {
        $headers = ($exception instanceof SymfonyHttpExceptionInterface || $exception instanceof HttpExceptionInterface) ? $exception->getHeaders() : [];

        return new self('An error occurred', $exception->getMessage(), $status, $exception->getTrace(), type: "/errors/$status", headers: $headers, previous: $exception->getPrevious());
    }

    #[Ignore]
    #[ApiProperty(readable: false)]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[Ignore]
    #[ApiProperty(readable: false)]
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    #[Groups(['jsonld', 'jsonproblem', 'jsonapi'])]
    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    #[Groups(['jsonld', 'jsonproblem', 'jsonapi'])]
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title = null): void
    {
        $this->title = $title;
    }

    #[Groups(['jsonld', 'jsonproblem', 'jsonapi'])]
    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    #[Groups(['jsonld', 'jsonproblem', 'jsonapi'])]
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail = null): void
    {
        $this->detail = $detail;
    }

    #[Groups(['jsonld', 'jsonproblem'])]
    public function getInstance(): ?string
    {
        return $this->instance;
    }

    public function setInstance(?string $instance = null): void
    {
        $this->instance = $instance;
    }
}
