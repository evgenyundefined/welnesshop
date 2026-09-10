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
    private const string PLACEHOLDER = 'ххххх';

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
                'body' => <<<'MD'
                    ## Как оформить заказ

                    Добавьте товары в корзину и заполните форму оформления. После подтверждения
                    заказа с вами свяжется менеджер.

                    ## Как узнать статус заказа

                    Статус виден в разделе «Заказы» личного кабинета.

                    ## Остались вопросы

                    Напишите нам — контакты указаны на странице «Контакты».
                    MD,
            ],
            [
                'slug' => 'dostavka-i-oplata',
                'title' => 'Доставка и оплата',
                'body' => <<<'MD'
                    ## Доставка

                    - **Курьером** — по адресу, который вы указали при оформлении.
                    - **Транспортной компанией** — до терминала в вашем городе.

                    Сроки и стоимость уточняйте у менеджера.

                    ## Оплата

                    Способ оплаты выбирается при оформлении заказа. Раздел заполняется
                    после подключения платёжного провайдера.
                    MD,
            ],
            [
                'slug' => 'obuchenie',
                'title' => 'Обучение',
                'body' => <<<'MD'
                    ## Материалы

                    Здесь размещаются обучающие материалы о продукции и её применении.

                    Раздел редактируется в админке: «Страницы» → «Обучение».
                    MD,
            ],
            [
                'slug' => 'kontakty',
                'title' => 'Контакты',
                'body' => <<<'MD'
                    ## Как с нами связаться

                    - Телефон: ххххх
                    - Почта: ххххх
                    - Мессенджеры: ххххх

                    ## Реквизиты

                    ххххх
                    MD,
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
            'promo_body' => <<<'MD'
                Мы собираем каталог пептидов, нутрицевтиков и устройств для домашнего ухода.

                ## Почему мы

                1. **Качество.** Работаем с проверенными поставщиками.
                2. **Поддержка.** Помогаем подобрать продукт под задачу.
                3. **Логистика.** Доставляем курьером и транспортными компаниями.

                Текст редактируется в админке: «Сайт» → «Блок над футером».
                MD,
            'disclaimer' => 'Здесь размещается ваш дисклеймер о назначении продукции. '
                .'Текст должен быть составлен под ваш бизнес — отредактируйте его в админке.',
            'contact_email' => 'xxxxx@example.com',
            'contact_phone' => self::PLACEHOLDER,
        ];
    }
}
