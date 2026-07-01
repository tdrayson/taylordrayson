<?php

it('serves the control panel login and leaves the timeline intact', function () {
    $this->get('/cp')->assertRedirect('/cp/auth/login');
    $this->get('/cp/auth/login')->assertOk();
    $this->get('/')->assertOk(); // existing timeline still resolves
});
