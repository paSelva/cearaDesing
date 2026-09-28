<?php

/*
| Parâmetros do Prêmio Ceará Tem Design 2026 que não mudam pelo painel.
| Datas e faixas ficam na tabela `editions` (editáveis no admin, §7).
*/

return [

    'entry' => [
        // Textos do §4: limite técnico (P75).
        'text_max_length' => 5000,

        // Links (P7, P75).
        'max_links' => 5,

        // Imagens (§4, P6).
        'images' => [
            'min' => 1,
            'max' => 10,
            'max_kb' => 5 * 1024,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // PDF complementar (P7, P45): opcional, 1 arquivo.
        'supplementary_pdf' => [
            'max_kb' => 10 * 1024,
        ],
    ],

];
