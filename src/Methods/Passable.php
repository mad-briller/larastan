<?php

declare(strict_types=1);

namespace NunoMaduro\Larastan\Methods;

use Illuminate\Contracts\Pipeline\Pipeline;
use LogicException;
use NunoMaduro\Larastan\Concerns;
use NunoMaduro\Larastan\Contracts\Methods\PassableContract;
use NunoMaduro\Larastan\Reflection\StaticMethodReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Php\PhpMethodReflectionFactory;
use PHPStan\Reflection\ReflectionProvider;

/**
 * @internal
 */
final class Passable implements PassableContract
{
    use Concerns\HasContainer;

    /**
     * @var \PHPStan\Reflection\Php\PhpMethodReflectionFactory
     */
    private $methodReflectionFactory;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var \Illuminate\Contracts\Pipeline\Pipeline
     */
    private $pipeline;

    /**
     * @var \PHPStan\Reflection\ClassReflection
     */
    private $classReflection;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @var \PHPStan\Reflection\MethodReflection|null
     */
    private $methodReflection;

    /**
     * @var bool
     */
    private $staticAllowed = false;

    /**
     * Method constructor.
     */
    public function __construct(
        PhpMethodReflectionFactory $methodReflectionFactory,
        ReflectionProvider $reflectionProvider,
        Pipeline $pipeline,
        ClassReflection $classReflection,
        string $methodName
    ) {
        $this->methodReflectionFactory = $methodReflectionFactory;
        $this->reflectionProvider = $reflectionProvider;
        $this->pipeline = $pipeline;
        $this->classReflection = $classReflection;
        $this->methodName = $methodName;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassReflection(): ClassReflection
    {
        return $this->classReflection;
    }

    /**
     * {@inheritdoc}
     */
    public function setClassReflection(ClassReflection $classReflection): PassableContract
    {
        $this->classReflection = $classReflection;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * {@inheritdoc}
     */
    public function hasFound(): bool
    {
        return $this->methodReflection !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function searchOn(string $class): bool
    {
        $classReflection = $this->reflectionProvider->getClass($class);

        $found = $classReflection->hasNativeMethod($this->methodName);

        if ($found) {
            $this->setMethodReflection($classReflection->getNativeMethod($this->methodName));
        }

        return $found;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethodReflection(): MethodReflection
    {
        if ($this->methodReflection === null) {
            throw new LogicException("MethodReflection doesn't exist");
        }

        return $this->methodReflection;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethodReflection(MethodReflection $methodReflection): void
    {
        $this->methodReflection = $methodReflection;
    }

    /**
     * {@inheritdoc}
     */
    public function setStaticAllowed(bool $staticAllowed): void
    {
        $this->staticAllowed = $staticAllowed;
    }

    /**
     * {@inheritdoc}
     */
    public function isStaticAllowed(): bool
    {
        return $this->staticAllowed;
    }

    /**
     * {@inheritdoc}
     */
    public function sendToPipeline(string $class, $staticAllowed = false): bool
    {
        $classReflection = $this->reflectionProvider->getClass($class);

        $this->setStaticAllowed($this->staticAllowed ?: $staticAllowed);

        $originalClassReflection = $this->classReflection;
        $this->pipeline->send($this->setClassReflection($classReflection))
            ->then(
                function (PassableContract $passable) use ($originalClassReflection) {
                    if ($passable->hasFound()) {
                        $this->setMethodReflection($passable->getMethodReflection());
                        $this->setStaticAllowed($passable->isStaticAllowed());
                    }

                    $this->setClassReflection($originalClassReflection);
                }
            );

        if ($result = $this->hasFound()) {
            $this->setMethodReflection(new StaticMethodReflection($this->getMethodReflection()));
        }

        return $result;
    }

    public function getReflectionProvider(): ReflectionProvider
    {
        return $this->reflectionProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethodReflectionFactory(): PhpMethodReflectionFactory
    {
        return $this->methodReflectionFactory;
    }
}
