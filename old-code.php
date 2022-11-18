<?php

// 2. Send a confirmation email to the charity
$email->setSubject('Gift of Giving Donation');
$email->addTo($charity['email'], $charity['name']);
$email->addContent(
    'text/html',
    sprintf(
        "%s %s has just donated you. We'll send the funds to you in the next weekly funds transfer.",
        $inputFilter->getValue('first_name'),
        $inputFilter->getValue('last_name'),
    )
);
$sendgrid = new \SendGrid($_SERVER['SENDGRID_API_KEY']);
try {
    $response = $sendgrid->send($email);
    printf("Response status: %d\n\n", $response->statusCode());
} catch (Exception $e) {
    echo 'Caught exception: '. $e->getMessage() ."\n";
}

// 3. Send a confirmation email to the person donating
$email->setSubject('Thank you for your donation');
$email->addTo(
    $inputFilter->getValue('email_address'),
    sprintf(
        "%s %s",
        $inputFilter->getValue('first_name'),
        $inputFilter->getValue('last_name'),
    )
);
$email->addContent(
    'text/html',
    sprintf(
        "Thank you, %s, for donating to <bold>%s</bold>. With your help, they'll be able to continue doing the valuable work that they do.",
        $inputFilter->getValue('first_name'),
        $charity['name'],
    )
);
$sendgrid = new \SendGrid($_SERVER['SENDGRID_API_KEY']);
try {
    $response = $sendgrid->send($email);
    printf("Response status: %d\n\n", $response->statusCode());
} catch (Exception $e) {
    echo 'Caught exception: '. $e->getMessage() ."\n";
}

