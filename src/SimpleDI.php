<?php

declare(strict_types=1);

namespace Otobio;

use Closure,
    ReflectionClass,
    ReflectionMethod,
    ReflectionException,
    Exception;

use Otobio\Exceptions\{
    BindingNotFoundException
};

use Psr\Container\ContainerInterface;

class SimpleDI implements ContainerInterface {

    protected
        $sharedInstances = [],
        $bindings = [];


    public function getBinding(string $id)
    {
        while (true) {
            if (isset($this->bindings[$id])) {
                $binding = $this->bindings[$id];
                if (is_string($binding['concrete']) && array_key_exists($binding['concrete'], $this->bindings)) {
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


    protected function clearResolved(string $id)
    {
        unset(
            $this->bindings[$id],
            $this->sharedInstances[$id]
        );
    }

    public function add(string $id, array $configuration = [])
    {
        $this->clearResolved($id);

        $this->bindings[$id] = array_merge([
            'concrete' => Closure::bind(function() use ($id) { return $id; }, null),
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

    public function resolve(string $id)
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
        $parameters = $constructor ? $this->getParams($constructor, $binding) : [];

        if (count($parameters)) {
            return function() use ($reflectorClass, $parameters) {
                return new $reflectorClass->name(...$parameters);
            };
        } else {
            return function () use ($reflectorClass) {
                return new $reflectorClass->name;
            };
        }
    }

    public function getParams(ReflectionMethod $method, array $binding): array
    {
        $resolvedParams = [];

    	foreach ($method->getParameters() as $index => $parameter) {
            if (array_key_exists($index, $binding['parameters']) || array_key_exists($parameter->getName(), $binding['parameters'])) {
                $resolvedParam = $binding['parameters'][$index] ?? $binding['parameters'][$parameter->getName()];
            } else {
    			$type = $parameter->getType();
                $isBuiltIn = $type instanceof \ReflectionNamedType && $type->isBuiltIn();
                if ($isBuiltIn) {
                    $resolvedParam = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                } else {
                    $resolvedParam = $this->resolve($type->getName());
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
    }
}
