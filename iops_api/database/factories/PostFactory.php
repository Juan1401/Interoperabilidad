<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Post;
use Faker\Generator as Faker;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->word(),
        'content' => $faker->text(100),
        'color' => $faker->hexColor(),
        'phone' => $faker->phoneNumber(),
        'author' => $faker->name,
        'author_age' => $faker->numberBetween(10, 80)
    ];
});
