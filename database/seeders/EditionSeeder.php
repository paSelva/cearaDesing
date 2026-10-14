<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Criterion;
use App\Models\Edition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EditionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $edition = Edition::query()->firstOrCreate(
                ['year' => Edition::YEAR],
                [
                    'name' => '1º Prêmio Ceará Tem Design 2026',
                    'registration_starts_at' => '2026-09-28 00:00:00',
                    'registration_ends_at' => '2026-10-28 23:59:59',
                    'judging_starts_at' => '2026-10-29 00:00:00',
                    'judging_ends_at' => '2026-11-20 23:59:59',
                    'voting_starts_at' => '2026-10-29 00:00:00',
                    'voting_ends_at' => '2026-11-20 23:59:59',
                    'results_announce_on' => '2026-11-21',
                    'ceremony_on' => '2026-11-26',
                    'project_period_starts_on' => '2022-01-01',
                    'project_period_ends_on' => '2026-10-28',
                    'judges_per_entry' => 3,
                    'band_gold_min' => 54,
                    'band_silver_min' => 48,
                    'band_bronze_min' => 42,
                    'band_honorable_min' => 36,
                ],
            );

            $standardCriteria = [
                'Conceito e criatividade',
                'Qualidade do projeto',
                'Adequação',
                'Relevância',
            ];
            $exportCriteria = [
                'Design e diferenciação',
                'Qualidade do produto',
                'Potencial de mercado',
                'Potencial de internacionalização',
            ];

            // Textos oficiais transcritos do §3 do edital disponível em docs/.
            $categories = [
                ['3.1', 'Design Gráfico e Comunicação Visual', 'design-grafico-comunicacao-visual', 'Identidades visuais, marcas, embalagens, publicações, projetos editoriais, sinalização, tipografia, comunicação visual, design da informação e projetos relacionados.', false],
                ['3.2', 'Design de Produto', 'design-de-produto', 'Mobiliário, iluminação, objetos, equipamentos, utensílios, artesanato contemporâneo, produtos industriais e projetos relacionados ao desenvolvimento de produtos.', false],
                ['3.3', 'Design de Moda', 'design-de-moda', 'Vestuário, coleções, fardamentos, bolsas, calçados, acessórios, joias, bijuterias, estamparia, superfícies e projetos relacionados.', false],
                ['3.4', 'Design Digital e Experiências Interativas', 'design-digital-experiencias-interativas', 'Aplicativos, sites, plataformas, UX/UI, jogos, interfaces, produtos digitais e experiências interativas.', false],
                ['3.5', 'Design Social, de Serviços e para Sustentabilidade', 'design-social-servicos-sustentabilidade', 'Design de serviços, inovação social, serviços e políticas públicas, sustentabilidade, desenvolvimento territorial, acessibilidade, inclusão e projetos voltados à melhoria de serviços e experiências.', false],
                ['3.6', 'Ilustração, Motion Design e Animação', 'ilustracao-motion-design-animacao', 'Ilustração autoral ou aplicada, design de personagens, motion design, animação, narrativas visuais e projetos relacionados.', false],
                ['3.7', 'Estudantes — Design e Novos Talentos', 'estudantes-design-novos-talentos', 'Projetos desenvolvidos por estudantes durante cursos técnicos, tecnológicos ou de graduação em Design. Podem participar projetos acadêmicos e Trabalhos de Conclusão de Curso — TCC. Caso o projeto seja premiado, a organização poderá solicitar a comprovação do vínculo do participante com a instituição de ensino no período em que o trabalho foi realizado.', true],
                ['3.8', 'Organizações e Instituições', 'organizacoes-instituicoes', 'Para empresas, instituições públicas ou privadas e organizações que utilizem o design para melhorar ou transformar produtos, serviços, processos, experiências, programas ou formas de atuação. A inscrição deverá mostrar qual foi a contribuição do design para o projeto e seus principais resultados.', false],
                ['3.9', 'Categoria Especial — Produto com Potencial de Exportação', 'produto-potencial-exportacao', 'Destinada a produtos cearenses que apresentem design, diferenciação, qualidade e potencial para alcançar mercados internacionais. Podem participar produtos de diferentes segmentos, como mobiliário, moda, acessórios, artesanato, objetos, utilidades e produtos industriais. Não é necessário que o produto já seja exportado. A avaliação será realizada por profissionais convidados com experiência relacionada a design, produtos, mercados e/ou internacionalização. Havendo parceria institucional confirmada, poderão participar da avaliação profissionais indicados pela ApexBrasil ou por outras instituições parceiras. A premiação reconhece o potencial de internacionalização do produto e não representa certificação, garantia de exportação ou ingresso automático em programas de instituições parceiras.', false],
            ];

            foreach ($categories as $index => [$code, $name, $slug, $description, $isStudentOnly]) {
                $category = Category::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'edition_id' => $edition->id,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'is_student_only' => $isStudentOnly,
                        'sort' => $index + 1,
                    ],
                );

                foreach (($code === '3.9' ? $exportCriteria : $standardCriteria) as $criterionIndex => $criterionName) {
                    Criterion::query()->firstOrCreate(
                        ['category_id' => $category->id, 'sort' => $criterionIndex + 1],
                        ['name' => $criterionName],
                    );
                }
            }
        });
    }
}
