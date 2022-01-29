<?php

declare(strict_types=1);

namespace Otobio;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use Otobio\Exceptions\BindingNotFoundException;
use Psr\Container\ContainerInterface;

class SimpleDI implements ContainerInterface
{
    protected $sharedInstances = [];
    protected $bindings = [];

    protected function getBinding(string $id)
    {
        while (true) {
            if (isset($this->bindings[$id])) {
                $binding = $this->bindings[$id];
                if (
                    is_string($binding['concrete']) &&
                    array_key_exists($binding['concrete'], $this->bindings) &&
                    $id !== $binding['concrete']
                ) {
                    $id = $binding['concrete'];
                    continue;
                }

                return $binding;
            } else {
                return null;
            }
        }
    }

    protected function isShared(string $id)
    {
        $binding = $this->getBinding($id);
        return isset($binding) && $binding['shared'] === true;
    }

    protected function resolve(string $id)
    {
        if (isset($this->sharedInstances[$id])) {
            return $this->sharedInstances[$id];
        }

        $output = $this->getClosure($id)($this);

        if ($this->isShared($id)) {
            $this->sharedInstances[$id] = $output;
        }

        return $output;
    }

    protected function getClosure(string $id)
    {
        $binding = $this->getBinding($id);

        if (!$binding) {
            $binding = [
                'concrete' => $id,
                'parameters' => []
            ];
        }

        if ($binding['concrete'] instanceof Closure) {
            return $binding['concrete'];
        }

        try {
            $reflectorClass = new ReflectionClass($binding['concrete']);
        } catch (ReflectionException $exc) {
            throw new BindingNotFoundException("Class {$binding['concrete']} does not exist");
        }

        if (!$reflectorClass->isInstantiable()) {
            throw new BindingNotFoundException("Instance of {$binding['concrete']} cannot be done, because {$binding['concrete']} is not instantiable");
        }

        $constructor = $reflectorClass->getConstructor();

        if ($constructor && count($constructor->getParameters())) {
            $closure = function ($container) use ($reflectorClass, $constructor, $binding) {
                return new $reflectorClass->name(...$container->getParams($constructor, $binding)($container));
            };
        } else {
            $closure = function () use ($reflectorClass) {
                return new $reflectorClass->name;
            };
        }

        $closure = Closure::bind($closure, null);

        // Future Improvement: Provide container option to save resolved configurations
        if ($this->has($id) && !$this->isShared($id)) {
            $binding['concrete'] = $closure;
            $this->add($id, $binding);
        }

        return $closure;
    }

    protected function getParams(ReflectionMethod $method, array $binding): Closure
    {
        $parameters = $method->getParameters();
        $overrideParameters = $binding['parameters'];

        return Closure::bind(function ($container) use ($parameters, $overrideParameters) {
            $resolvedParams = [];

            foreach ($parameters as $index => $parameter) {
                if (array_key_exists($index, $overrideParameters) || array_key_exists($parameter->getName(), $overrideParameters)) {
                    $resolvedParam = $overrideParameters[$index] ?? $overrideParameters[$parameter->getName()];
                } else {
                    $type = $parameter->getType();
                    $isBuiltIn = $type instanceof \ReflectionNamedType && $type->isBuiltIn();
                    if ($isBuiltIn || $parameter->isOptional()) {
                        $resolvedParam = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                    } else {
                        $resolvedParam = $container->resolve($type->getName());
                    }
                }

                if ($parameter->isVariadic()) {
                    $resolvedParams[] = [$result];
                    break;
                } else {
                    $resolvedParams[] = $resolvedParam;
                }
            }

            return $resolvedParams;
        }, null);
    }

    public function add(string $id, array $configuration = [])
    {
        unset(
            $this->bindings[$id],
            $this->sharedInstances[$id]
        );

        $this->bindings[$id] = array_merge([
            'concrete' => Closure::bind(function () use ($id) {
                return $id;
            }, null),
            'shared' => false,
            'parameters' => [],
        ], $configuration);

        return $this;
    }

    public function get(string $id)
    {
        return $this->resolve($id);
    }

    public function has(string $id): bool
    {
        return (bool) $this->getBinding($id);
    }
}
