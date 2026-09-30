<?php

// Остальные правила берутся из lang/en/validation.php.
return [
    'required' => 'Поле «:attribute» обязательно.',
    'email' => 'Введите корректный email.',
    'min' => [
        'string' => 'Поле «:attribute» должно быть не короче :min символов.',
        'numeric' => 'Поле «:attribute» должно быть не меньше :min.',
    ],
    'max' => [
        'string' => 'Поле «:attribute» должно быть не длиннее :max символов.',
        'file' => 'Файл слишком большой.',
    ],
    'confirmed' => 'Пароли не совпадают.',
    'unique' => 'Такое значение уже занято.',
    'url' => 'Введите корректную ссылку (https://…).',
    'integer' => 'Поле «:attribute» должно быть числом.',
    'between' => [
        'numeric' => 'Поле «:attribute» должно быть от :min до :max.',
    ],
    'current_password' => 'Неверный пароль.',
    'password' => [
        'min' => 'Пароль должен быть не короче :min символов.',
    ],
    'in' => 'Выбрано неверное значение.',
    'file' => 'Нужно выбрать файл.',
    'mimetypes' => 'Неподдерживаемый тип файла.',
    'attributes' => [
        'name' => 'Имя',
        'email' => 'Email',
        'password' => 'Пароль',
        'body' => 'Сообщение',
        'year' => 'Год',
        'title' => 'Название',
    ],
];
