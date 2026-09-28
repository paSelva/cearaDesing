<?php

declare(strict_types=1);

namespace App\Enums;

/** Declarações e autorização do §8, um aceite por item (P11). Textos do edital. */
enum ConsentType: string
{
    case Veracidade = 'veracidade';
    case AutorizacaoInscricao = 'autorizacao_inscricao';
    case Creditos = 'creditos';
    case UsoImagensMateriais = 'uso_imagens_materiais';
    case RegrasEdital = 'regras_edital';
    case AutorizacaoDivulgacao = 'autorizacao_divulgacao';

    public function label(): string
    {
        return match ($this) {
            self::Veracidade => 'Confirmo que as informações apresentadas são verdadeiras.',
            self::AutorizacaoInscricao => 'Confirmo que possuo autorização para inscrever o projeto.',
            self::Creditos => 'Confirmo que informei corretamente a autoria e os principais créditos.',
            self::UsoImagensMateriais => 'Confirmo que possuo autorização para utilizar as imagens e materiais enviados.',
            self::RegrasEdital => 'Aceito as regras do edital do 1º Prêmio Ceará Tem Design.',
            self::AutorizacaoDivulgacao => 'Autorizo a utilização das imagens, informações e créditos enviados para avaliação, divulgação, exposição, registro e memória institucional do Prêmio Ceará Tem Design, sempre relacionados à realização e divulgação do prêmio.',
        };
    }
}
