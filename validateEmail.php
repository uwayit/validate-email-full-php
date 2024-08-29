<?php
//
// Бібліотека якісної валідації email на стороні сервера

class validateEmail
    {

    private $email = '';
    private $error = [];


    
    function __construct($email)
        {
            $this->email = $this->buildStandartEmail($this->email);
        }




    // Ми відштовхуємося від логіки, що:
    // 1. email це унікальний ідентифікатор користувача
    // 2. під одним email можна зареєструвати лише один аккаунт
    // В зв'язку з цим ми закриваємо цією функцією всі можливості множинних використань одного email
    static public function buildStandartEmail($email)
        {
        // Видаляємо пробіли на початку і в кінці рядка та всі пробіли всередині рядка
        $email = trim($email);
        $email = preg_replace('/\s+/', '', $email);
        $email = strtolower($email);

        // За замовчуванням так і запишемо якщо нічого не змінимо
        $StandartEmail = $email;

        // Конвертуємо всі можливі варіації Яндекс ящиків в еталонний формат mail.mail@yandex.ru
        // Не найкраще рішення використовувати .[a-z]{2,3} але зато коротко
        if (preg_match('/@(?:yandex\.[a-z]{2,3}|ya\.ru|narod\.ru)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('-', '.', $box) . '@yandex.ru';
            }

        // Конвертуємо всі можливі варіації ГУГЛ ящиків на еталонний формат mailmail@gmail.com
        if (preg_match('/@(gmail\.com|googlemail\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@gmail.com';
            }

        // Конвертуємо всі можливі варіації ProtonMail ящиків на еталонний формат box@proton.me
        if (preg_match('/@(pm\.me|proton\.me|protonmail\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@proton.me';
            }
        // ВИдаляємо точки в icloud
        if (preg_match('/@(icloud\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@icloud.com';
            }
        // Конвертуємо yahoo
        if (preg_match('/@(ymail\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@yahoo.com';
            }
        return $StandartEmail;
        }


    // Відаємо опрацьований email
    public function getEmail()
        {
        return $this->email;
        }
    // Повідомляємо, чи були якісь помилки підчас опрацювання email
    public function getError()
        {
        return $this->error;
        }




    } // class 

