<?php

namespace App\Contracts;

interface HostResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
