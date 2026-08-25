<?php

class Dado
{
    public function rolar(int $faces = 6): int
    {
        return random_int(1, $faces);
    }
}
