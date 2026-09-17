<?php

namespace App\Tests;

use App\DTO\MessageDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageDtoTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->validator = self::getContainer()->get(
            ValidatorInterface::class
        );
    }

    public function testValidMessageDtoHasNoViolations(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            'Тестовый комментарий',
            '192.168.1.100',
            'Mozilla/5.0',
        );

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testNameCannotBeBlank(): void
    {
        $dto = new MessageDto(
            '',
            'ivan@example.com',
            '+79991234567',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Имя не может быть пустым'
        );
    }

    public function testNameMustContainAtLeastTwoCharacters(): void
    {
        $dto = new MessageDto(
            'И',
            'ivan@example.com',
            '+79991234567',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Имя должно содержать минимум 2 символа'
        );
    }

    public function testNameCannotContainMoreThanFortyCharacters(): void
    {
        $dto = new MessageDto(
            str_repeat('А', 41),
            'ivan@example.com',
            '+79991234567',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Имя должно содержать максимум 40 символов'
        );
    }

    public function testEmailCannotBeBlank(): void
    {
        $dto = new MessageDto(
            'Иван',
            '',
            '+79991234567',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Email не может быть пустым'
        );
    }

    public function testEmailMustHaveValidFormat(): void
    {
        $dto = new MessageDto(
            'Иван',
            'not-an-email',
            '+79991234567',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Некорректный формат email'
        );
    }

    public function testEmailCannotContainMoreThanOneHundredCharacters(): void
    {
        $dto = new MessageDto(
            'Иван',
            str_repeat('a', 91) . '@example.com',
            '+79991234567',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Email должен содержать максимум 100 символов'
        );
    }

    public function testPhoneCannotBeBlank(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Телефон не может быть пустым'
        );
    }

    public function testPhoneMustHaveValidFormat(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            'abc123',
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Некорректный формат телефона'
        );
    }

    public function testPhoneCannotContainMoreThanTwentyCharacters(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            str_repeat('1', 21),
            'Комментарий',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Некорректный формат телефона'
        );
    }

    public function testIpCannotContainMoreThanFortyFiveCharacters(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            'Комментарий',
            str_repeat('1', 46),
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'IP должен содержать максимум 45 символов'
        );
    }

    public function testUserAgentCannotContainMoreThanFiveHundredCharacters(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            'Комментарий',
            null,
            str_repeat('A', 501),
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'User-Agent должен содержать максимум 500 символов'
        );
    }

    public function testCommentCannotBeBlank(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            '',
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Комментарий не может быть пустым'
        );
    }

    public function testCommentCannotContainMoreThanTwoHundredFiftyFiveCharacters(): void
    {
        $dto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            str_repeat('А', 256),
        );

        $violations = $this->validator->validate($dto);

        $this->assertViolationMessage(
            $violations,
            'Комментарий должен содержать максимум 255 символов'
        );
    }

    private function assertViolationMessage(
        \Symfony\Component\Validator\ConstraintViolationListInterface $violations,
        string $expectedMessage,
    ): void {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        $this->assertContains($expectedMessage, $messages);
    }
}
