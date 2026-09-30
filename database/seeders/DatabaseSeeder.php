<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\YouTube;
use Illuminate\Database\Seeder;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Стартовое содержимое сайта. Всё редактируется в админке (Настройки).
     */
    public function run(): void
    {
        $defaults = [
            'name' => ['hy' => 'notfilmmaker', 'ru' => 'notfilmmaker', 'en' => 'notfilmmaker'],
            'hero_name' => ['hy' => 'not filmmaker', 'ru' => 'not filmmaker', 'en' => 'not filmmaker'],
            'real_name' => ['hy' => 'Հրաչ Հարությունյան', 'ru' => 'Грач Арутюнян', 'en' => 'Hrach Harutyunyan'],
            'hero_kicker' => ['hy' => 'Հրաչ Հարությունյան · Կինոգործիչ Հայաստանից', 'ru' => 'Грач Арутюнян · Кинематографист из Армении', 'en' => 'Hrach Harutyunyan · Filmmaker from Armenia'],
            'hero_title' => [
                'hy' => 'Ռեժիսոր · Օպերատոր · Լուսանկարիչ · Մոնտաժող',
                'ru' => 'Режиссёр · Оператор · Фотограф · Монтажёр',
                'en' => 'Director · Cinematographer · Photographer · Editor',
            ],
            'about_title' => ['hy' => 'Հրաչ Հարությունյան', 'ru' => 'Грач Арутюнян', 'en' => 'Hrach Harutyunyan'],
            'about_text' => [
                'hy' => "Ես Հրաչն եմ՝ notfilmmaker։ Ռեժիսոր եմ, օպերատոր, լուսանկարիչ և մոնտաժող Հայաստանից։ Նկարահանում եմ ֆիլմեր, կարճամետրաժներ, սերիալներ և տեսահոլովակներ՝ գաղափարից մինչև վերջնական մոնտաժ և գունային ուղղում։\n\nԻնձ համար յուրաքանչյուր կադր պատմություն է․ լույսը, ռիթմը և զգացմունքը պետք է աշխատեն միասին, որպեսզի դիտողը հավատա տեսածին։",
                'ru' => "Меня зовут Грач, в сети я notfilmmaker. Я режиссёр, оператор, фотограф и монтажёр из Армении. Снимаю фильмы, короткометражки, сериалы и музыкальные клипы: от идеи до финального монтажа и цветокоррекции.\n\nДля меня каждый кадр — это история: свет, ритм и эмоция должны работать вместе, чтобы зритель поверил в то, что видит.",
                'en' => "I'm Hrach, known online as notfilmmaker. I am a director, cinematographer, photographer and editor from Armenia. I make films, short films, series and music videos, from the first idea to the final cut and color grade.\n\nFor me every frame is a story: light, rhythm and emotion have to work together so the viewer believes what they see.",
            ],
            'location' => ['hy' => 'Երևան, Հայաստան', 'ru' => 'Ереван, Армения', 'en' => 'Yerevan, Armenia'],
            'instagram' => 'https://www.instagram.com/not_filmmaker/',
            'facebook' => 'https://www.facebook.com/HrachFromArmenia',
            'youtube' => 'https://www.youtube.com/@notfilmmaker',
            'youtube_channel_id' => 'UCGyQ4Cefeuq_ugNfh1QYQpw',
            'youtube_autosync' => true,
            'registration_open' => false,
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::find($key) === null) {
                Setting::put($key, $value);
            }
        }

        // Первые работы — последние видео с YouTube-канала.
        try {
            YouTube::import($defaults['youtube_channel_id']);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
