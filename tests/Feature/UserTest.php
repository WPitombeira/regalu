<?php

use App\Models\User;

pest()->group('user');

describe('profile related', function() {
    it('user can view their own profile', function() {

    })->todo(note: <<<'NOTE'
        Given I am a user
        When I visit my profile page
        Then I should see my profile information
        NOTE);

    it('user can view other users profile', function() {

    })->todo(note: <<<'NOTE'
        Given I am a user
        When I visit another users profile page
        Then I should see their profile information if their profile is public
        NOTE);

    it('user can edit their own profile', function() {

    })->todo(note: <<<'NOTE'
        Given I am a user
        When I visit my profile page
        And I click the edit button
        Then I should see a form to edit my profile
        NOTE);

    it('admin user can edit other users profile', function() {

    })->todo(note: <<<'NOTE'
        Given I am a user
        And Given I am an admin
        When I visit another users profile page
        And I click the edit button
        Then I should see a form to edit their profile
        NOTE);

    it('normal user cannot edit other users profile', function() {

    })->todo(note: <<<'NOTE'
        Given I am a user
        When I visit another users profile page
        Then I should not see an edit button
        NOTE);

    it('settings can be acessed by authenticated users', function() {
        $User = User::factory()->create();
    
        $this->actingAs($User)
            ->get(route('settings'))
            ->assertOk();
    })->wip();
    
    it('user has a profile page', function() {
    
    })->todo();
})->wip();