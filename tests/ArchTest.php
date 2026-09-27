<?php

arch()->preset()->php();
arch()->preset()->security();

arch('não usa funções de depuração no código da aplicação')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
