<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\Middleware\Exception\BadUriPrefixException;

use function is_string;
use function strlen;
use function substr;

/**
 * This middleware supports routing when webroot is not the same folder as public.
 */
final class SubFolder implements MiddlewareInterface
{
    /**
     * @param UrlGeneratorInterface $uriGenerator The URI generator instance.
     * @param Aliases $aliases The aliases instance.
     * @param string|null $prefix URI prefix the specified immediately after the domain part.
     * The prefix value usually begins with a slash and must not end with a slash.
     * @param string|null $alias The path alias {@see Aliases::get()}.
     */
    public function __construct(private UrlGeneratorInterface $uriGenerator, private Aliases $aliases, private ?string $prefix = null, private ?string $alias = null)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadUriPrefixException If wrong URI prefix.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $prefix = $this->prefix;
        $auto = $prefix === null;
        /** @var string $prefix */
        $length = $auto ? 0 : strlen($prefix);

        if ($auto) {
            // automatically checks that the project is in a subfolder
            // and URI contains a prefix
            $scriptName = $request->getServerParams()['SCRIPT_NAME'];

            if (is_string($scriptName) && str_contains($scriptName, '/')) {
                $position = strrpos($scriptName, '/');
                $tmpPrefix = substr($scriptName, 0, $position === false ? null : $position);

                if (str_starts_with($path, $tmpPrefix)) {
                    $prefix = $tmpPrefix;
                    $length = strlen($prefix);
                }
            }
        } elseif ($length > 0) {
            /** @var string $prefix */
            if ($prefix[-1] === '/') {
                throw new BadUriPrefixException('Wrong URI prefix value.');
            }

            if (!str_starts_with($path, $prefix)) {
                throw new BadUriPrefixException('URI prefix does not match.');
            }
        }

        if ($length > 0) {
            $newPath = substr($path, $length);

            if ($newPath === '') {
                $newPath = '/';
            }

            if ($newPath[0] !== '/') {
                if (!$auto) {
                    throw new BadUriPrefixException('URI prefix does not match completely.');
                }
            } else {
                $request = $request->withUri($uri->withPath($newPath));
                /** @var string $prefix */
                $this->uriGenerator->setUriPrefix($prefix);

                if ($this->alias !== null) {
                    $this->aliases->set($this->alias, $prefix . '/');
                }
            }
        }

        return $handler->handle($request);
    }
}
