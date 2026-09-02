<?php

use App\Enums\PhotoTagRole;
use App\Enums\ReviewKind;
use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;

it('gives every kind a url word and a plural', function () {
    expect(SubjectKind::Person->segment())->toBe('people')
        ->and(SubjectKind::Pet->segment())->toBe('pets')
        ->and(SubjectKind::Spot->segment())->toBe('spots')
        ->and(SubjectKind::Thing->segment())->toBe('things')
        ->and(SubjectKind::Person->plural())->toBe('People');
});

it('pairs every category with exactly one kind', function () {
    foreach (SubjectCategory::cases() as $category) {
        expect($category->kind())->toBeInstanceOf(SubjectKind::class);
    }
});

it('takes the entry phrase from the category, falling back to the kind', function () {
    expect(SubjectCategory::Family->phrase())->toBe('With')
        ->and(SubjectCategory::Pub->phrase())->toBe('At')
        ->and(SubjectCategory::Car->phrase())->toBeNull()
        ->and(SubjectKind::Person->phrase())->toBe('With');
});

it('names the property path a review writes to', function () {
    expect(ReviewKind::Subjects->property())->toBe('reviewed.subjects');
});

it('has two photo tag roles', function () {
    expect(PhotoTagRole::cases())->toHaveCount(2);
});
