<?php

namespace Database\Seeders;

use App\Actions\Site\LoadSiteSettings;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starting content only. Everything here is created once and never rewritten,
 * so re-running this on every deploy cannot undo an editor's work.
 */
class SiteContentSeeder extends Seeder
{
    public function __construct(private readonly LoadSiteSettings $loadSiteSettings) {}

    public function run(): void
    {
        foreach ($this->pages() as $position => $page) {
            Page::query()->firstOrCreate(['slug' => $page['slug']], [...$page, 'position' => $position + 1]);
        }

        $settings = ($this->loadSiteSettings)();

        $settings->forceFill(
            collect($this->settings())
                ->reject(fn (mixed $value, string $field): bool => $settings->{$field} !== null)
                ->all(),
        )->save();
    }

    /** @return list<array{slug: string, title: string, body: string}> */
    private function pages(): array
    {
        return [
            [
                'slug' => 'vopros-otvet',
                'title' => 'Вопрос-ответ',
                'body' => <<<'HTML'
                    <h2>Как оформить заказ</h2>
                    <p>Добавьте товары в корзину и заполните форму оформления. После подтверждения
                    заказа с вами свяжется менеджер.</p>
                    <h2>Как узнать статус заказа</h2>
                    <p>Статус виден в разделе «Заказы» личного кабинета.</p>
                    <h2>Остались вопросы</h2>
                    <p>Напишите нам — контакты указаны на странице «Контакты».</p>
                    HTML,
            ],
            [
                'slug' => 'dostavka-i-oplata',
                'title' => 'Доставка и оплата',
                'body' => <<<'HTML'
                    <h2>Доставка</h2>
                    <ul>
                    <li><strong>Курьером</strong> — по адресу, который вы указали при оформлении.</li>
                    <li><strong>Транспортной компанией</strong> — до терминала в вашем городе.</li>
                    </ul>
                    <p>Сроки и стоимость уточняйте у менеджера.</p>
                    <h2>Оплата</h2>
                    <p>Способ оплаты выбирается при оформлении заказа. Раздел заполняется
                    после подключения платёжного провайдера.</p>
                    HTML,
            ],
            [
                'slug' => 'obuchenie',
                'title' => 'Обучение',
                'body' => <<<'HTML'
                    <h2>Материалы</h2>
                    <p>Здесь размещаются обучающие материалы о продукции и её применении.</p>
                    <p>Раздел редактируется в админке: «Страницы» → «Обучение».</p>
                    HTML,
            ],
            [
                'slug' => 'kontakty',
                'title' => 'Контакты',
                'body' => <<<'HTML'
                    <h2>Как с нами связаться</h2>
                    <ul>
                    <li>Телефон: ххххх</li>
                    <li>Почта: ххххх</li>
                    <li>Мессенджеры: ххххх</li>
                    </ul>
                    <h2>Реквизиты</h2>
                    <p>ххххх</p>
                    HTML,
            ],
        ];
    }

    /** @return array<string, string> */
    private function settings(): array
    {
        return [
            'banner_title' => 'agelesscode',
            'banner_subtitle' => 'Пептиды и велнес-технологии для лучшей версии вас',
            'banner_button_label' => 'Смотреть каталог',
            'banner_button_url' => '/',
            'promo_heading' => 'agelesscode — пептиды и велнес-технологии',
            'promo_body' => <<<'HTML'
                <p>Мы собираем каталог пептидов, нутрицевтиков и устройств для домашнего ухода.</p>
                <h2>Почему мы</h2>
                <ol>
                <li><strong>Качество.</strong> Работаем с проверенными поставщиками.</li>
                <li><strong>Поддержка.</strong> Помогаем подобрать продукт под задачу.</li>
                <li><strong>Логистика.</strong> Доставляем курьером и транспортными компаниями.</li>
                </ol>
                <p>Текст редактируется в админке: «Сайт» → «Блок над футером».</p>
                HTML,
            'disclaimer' => 'Здесь размещается ваш дисклеймер о назначении продукции. '
                .'Текст должен быть составлен под ваш бизнес — отредактируйте его в админке.',
            'contacts_body' => <<<'HTML'
                <p><strong>ххххх ххххх</strong></p>
                <p><a href="mailto:xxxxx@example.com">xxxxx@example.com</a></p>
                <p>Блок редактируется в админке: «Сайт» → «Контакты в футере».</p>
                HTML,
        ];
    }
}
