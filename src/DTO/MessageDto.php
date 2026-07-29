<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class MessageDto
{
    public ?int $id = null;

    #[Assert\NotBlank(message: 'Имя не может быть пустым')]
    #[Assert\Length(
        min: 2,
        max: 40,
        minMessage: 'Имя должно содержать минимум {{ limit }} символа',
        maxMessage: 'Имя должно содержать максимум {{ limit }} символов'
    )]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Email не может быть пустым')]
    #[Assert\Email(message: 'Некорректный формат email')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Email должен содержать максимум {{ limit }} символов'
    )]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Телефон не может быть пустым')]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,20}$/',
        message: 'Некорректный формат телефона'
    )]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Телефон должен содержать максимум {{ limit }} символов'
    )]
    public ?string $phone = null;

    #[Assert\Length(
        max: 45,
        maxMessage: 'IP должен содержать максимум {{ limit }} символов'
    )]
    public ?string $ip = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'User-Agent должен содержать максимум {{ limit }} символов'
    )]
    public ?string $agent = null;

    #[Assert\NotBlank(message: 'Комментарий не может быть пустым')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Комментарий должен содержать минимум {{ limit }} символ',
        maxMessage: 'Комментарий должен содержать максимум {{ limit }} символов'
    )]
    public ?string $comment = null;

    public function __construct(
        string $name,
        string $email,
        string $phone,
        string $comment,
        ?string $ip = null,
        ?string $agent = null,
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->comment = $comment;
        $this->ip = $ip;
        $this->agent = $agent;

    }
}
