<?php
namespace Dukhanin\Acl\Contracts\Auth;

use Dukhanin\Support\Contracts\Morphable;

interface AccessManager
{
    public function preloadEnabled(bool $switch = true);

    public function preload(Morphable $object = null, string $ability = null, Morphable $subject = null);

    public function can(Morphable $object, string $ability, Morphable $subject = null);

    public function exact(Morphable $object, string $ability, Morphable $subject = null);

    public function cannot(Morphable $object, string $ability, Morphable $subject = null);

    public function set(Morphable $object = null, string $ability = null, Morphable $subject = null, $value = null);
}