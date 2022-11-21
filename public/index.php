<?php

use DI\Container;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\I18n\Validator\IsInt;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\CreditCard;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SendGrid\Mail\Mail;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\StripeClient;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twilio\Rest\Client;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Load environment variables from .env in the project's
 * top-level directory
 */
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$container = new Container;

/**
 * An InputFilter for filtering and validating the form information
 */
$container->set(InputFilter::class, function(): InputFilter {
    $charity = new Input('charity');
    $charity->getValidatorChain()
        ->attach(new NotEmpty())
        ->attach(new InArray([
            'strict' => InArray::COMPARE_STRICT,
            'haystack' => [
                'asrc',
                'mjfpr',
                'msf',
                'pcrf',
                'rr',
                'tbb',
            ]
        ]));
    $charity->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $emailAddress = new Input('email_address');
    $emailAddress->getValidatorChain()
        ->attach(new NotEmpty())
        ->attach(new EmailAddress());
    $emailAddress->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $firstName = new Input('first_name');
    $firstName->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $firstName->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $lastName = new Input('last_name');
    $lastName->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $lastName->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $address = new Input('address');
    $address->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $address->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $apartment = new Input('apartment');
    $apartment->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $apartment->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());
    $apartment->setRequired(false);

    $city = new Input('city');
    $city->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $city->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $state = new Input('state');
    $state->setRequired(false);
    $state->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $state->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $country = new Input('country');
    $country->getValidatorChain()
        ->attach(new StringLength(['min' => 2]));
    $country->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $postalCode = new Input('postcode');
    $postalCode->getValidatorChain()
        ->attach(new StringLength(['min' => 4]));
    $postalCode->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $cardName = new Input('card_name');
    $cardName->getValidatorChain()
        ->attach(new StringLength(['min' => 5]));
    $cardName->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $cardNumber = new Input('card_number');
    $cardNumber->getValidatorChain()
        ->attach(new CreditCard());
    $cardNumber->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $cardExpirationDate = new Input('card_expiration_date');
    $cardExpirationDate->getValidatorChain()
        ->attach(new Regex(['pattern' => '/\d{2}\/\d{2}/']));
    $cardExpirationDate->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $cardCVC = new Input('card_cvc');
    $cardCVC->getValidatorChain()
        ->attach(new IsInt())
        ->attach(new Regex(['pattern' => '/\d{3,4}/']));
    $cardCVC->getFilterChain()
        ->attach(new StringTrim())
        ->attach(new StripTags());

    $inputFilter = new InputFilter();
    $inputFilter
        ->add($charity)
        ->add($emailAddress)
        ->add($firstName)
        ->add($lastName)
        ->add($address)
        ->add($apartment)
        ->add($city)
        ->add($state)
        ->add($country)
        ->add($postalCode)
        ->add($cardName)
        ->add($cardNumber)
        ->add($cardExpirationDate)
        ->add($cardCVC);

    return $inputFilter;
});

$container->set('view', function() {
    $twig = Twig::create(
        __DIR__ . '/../src/templates/',
        ['cache' => false]);
    $twig->addExtension(new \Twig\Extra\Markdown\MarkdownExtension());
    $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
        public function load($class) {
            if (MarkdownRuntime::class === $class) {
                return new MarkdownRuntime(new DefaultMarkdown());
            }
        }
    });

    return $twig;
});

/**
 * The Twilio Client object for interacting with the Programmable SMS API.
 *
 * @see https://www.twilio.com/docs/sms/tutorials/how-to-send-sms-messages-php
 */
$container->set(Client::class, function (): Client {
    return new Client(
        $_SERVER["TWILIO_ACCOUNT_SID"],
        $_SERVER["TWILIO_AUTH_TOKEN"]
    );
});

$container->set(StripeClient::class, function (): StripeClient {
    return new StripeClient($_SERVER['STRIPE_API_KEY']);
});

$container->set('charities', function(): array {
    $asrcDescription = <<<EOF
[The Asylum Seeker Resource Centre (ASRC)](https://asrc.org.au) is Australia's largest human rights organisation providing support to people seeking asylum. 

They are an independent not-for-profit organisation whose programs support and empower people seeking asylum to maximise their own physical, mental and social well-being. 

They champion the rights of people seeking asylum and mobilise a community of compassion to create lasting social and policy change.
EOF;

    $mjfDescription = <<<EOF
[The Michael J. Fox Foundation for Parkinson's Research](https://www.michaeljfox.org/) exists for one reason: to accelerate the next generation of Parkinson’s disease (PD) treatments. 

In practice, that means identifying and funding projects most vital to patients; spearheading solutions around seemingly intractable field-wide challenges; coordinating and streamlining the efforts of multiple, often disparate, teams; and doing whatever it takes to drive faster knowledge turns for the benefit of every life touched by PD.
EOF;

$mecfsDescription = <<<EOF
[The ME-CFS Portal](https://www.me-cfs.net/) is the largest self-help group in German-speaking countries for people with [Myalgic Encephalomyelitis / Chronic Fatigue Syndrome (ME/CFS)](https://www.nhs.uk/conditions/chronic-fatigue-syndrome-cfs/) and [Long-COVID](https://www.cdc.gov/coronavirus/2019-ncov/long-term-effects/index.html). 

The ME-CFS Portal provides comprehensive information and support to people with ME/CF, those who suspect that they may have it, and those wanting to know more about it. 

Have you been diagnosed with ME/CF or think that you may suffer from it but don't know what to do? Do you know someone with ME/CF and want to learn more about it? The ME-CFS Portal is the resource you need!
EOF;

$pcrfDescription = <<<EOF
The core mission of [the Australian Pancreatic Cancer Foundation (PanKind)](https://pankind.org.au/) as a cancer support in Australia is to improve the survival rates and quality of life for pancreatic cancer patients and their families. 

It takes a strategic and collaborative approach to addressing the challenges of pancreatic cancer. We focus on raising awareness, providing support and investing in ground-breaking research.
EOF;

$rrDescription = <<<EOF
[Reuben's Retreat](https://www.reubensretreat.org/) walks side-by-side, offering emotional and practical help through family support charity to families of child loss or those that have a child who is complexly poorly and may face an uncertain future. 

It enables families to create memories cocooned in the sanctuary of Reuben’s Retreat underpinned by their army of love and compassionate hearts.
EOF;

$tbbDescription = <<<EOF
[The Birthday Bank](https://thebirthdaybank.org.uk/) provides you with a birthday celebration pack, containing gifts, a cake, and the other essentials that you need to celebrate your child’s special day, such as cards, wrap, candles, decorations and the all-important badge! They show families in need that they are supported and valued by friends in their community
EOF;

    return [
        'asrc' => [
            'image' => 'asrc-background.jpg',
            'name' => 'The Asylum Seeker Resource Center',
            'description' => $asrcDescription,
            'actions' => [
                'Legal aid to those in refugee detention',
                'Advocacy to people in onshore and offshore detention',
                'Train young advocates to tell their stories',
                'Education and training including english language skills and training and professional development courses',
                'Employment programs which build skills, confidence and agency',
                'Paid internships for people seeking asylum who want to develop skills in program evaluation',
            ],
            'email' => 'admin@asrc.org.au',
            'website' => 'https://asrc.org.au',
            'social' => [
                'instagram' => 'asrc1',
                'linkedin' => 'company/asylum-seeker-resource-centre',
                'twitter' => 'ASRC1',
            ]
        ],
        'mjfpr' => [
            'image' => 'michael-j-fox.jpg',
            'name' => "The Michael J. Fox Foundation",
            'description' => $mjfDescription,
            'email' => 'info@michaeljfox.org',
            'website' => 'https://www.michaeljfox.org/',
            'actions' => [
                "Build improved knowledge about the lived experience of Parkinson's disease",
                "Find an objective test for Parkinson's",
                "Engage patients in research",
                "Support the development of new treatments and a cure",
            ],
            'social' => [
                'instagram' => 'michaeljfoxorg',
                'linkedin' => 'company/michaeljfoxorg',
                'twitter' => 'MichaelJFoxOrg',
            ]
        ],
        'mecfs' => [
            'image' => 'me-cfs-portal.jpg',
            'name' => 'ME-CFS Portal (German)',
            'description' => $mecfsDescription,
            'email' => 'program_inquiries@newyork.msf.org',
            'website' => 'https://www.me-cfs.net/',
            'actions' => [
                "Provides details about symptoms, diagnosis, important research, available therapies",
                "Support when dealing with government agencies and departments",
                "A forum to talk with, support, and receive support from others",
                "A regularly updated blog on everything related to ME/CF",
                "A database of knowledge about ME/CF as shared by others"
            ],
            'social' => [
                'instagram' => 'me_cfs_portal',
                'twitter' => 'MECFS_Portal',
                'youtube' => 'UCRhCLjPGVo1ZlpsU94xu-8g'
            ]
        ],
        'pcrf' => [
            'image' => 'pancreatic-cancer-foundation.jpg',
            'name' => 'The Australian Pancreatic Cancer Research Foundation',
            'description' => $pcrfDescription,
            'email' => ' info@pankind.org.au',
            'website' => 'https://pankind.org.au/',
            'actions' => [
                "Invest in research to accelerate treatments and improve survival",
                "Advocate on behalf of the pancreatic cancer community for equitable optimal and earlier access to diagnosis, treatment, and care",
                "Increase awareness of pancreatic cancer to support earlier diagnosis, and raise funds for research",
                "Support patients and carers through comprehensive information, resources, and links to support services"
            ],
            'social' => [
                'instagram' => 'pankind_apcf',
                'twitter' => 'PanKind_APCF',
            ]
        ],
        'rr' => [
            'image' => 'ruebens-retreat.jpg',
            'name' => "Reuben's Retreat",
            'description' => $rrDescription,
            'email' => 'contact@reubensretreat.org',
            'website' => 'https://www.reubensretreat.org/',
            'actions' => [
                "Provide practical and emotional support and promise to walk side by side with a family on their journey",
                "Deliver a bespoke and tailor-made support package for each family and guidance to help them navigate their journey",
                "Peer groups which enable parents of loss to come together, share their story and gain peer support",
                "Counselling and talking therapies can help families to navigate the raw and painful emotions",
            ],
            'social' => [
                'instagram' => 'reubensretreat',
                'linkedin' => 'company/3342334',
                'twitter' => 'ReubensRetreat',
            ]
        ],
        'tbb' => [
            'image' => 'the-birthday-bank.jpg',
            'name' => 'The Birthday Bank',
            'description' => $tbbDescription,
            'email' => 'laura@thebirthdaybank.org.uk',
            'website' => 'https://thebirthdaybank.org.uk/',
            'social' => [
                'instagram' => 'thebirthdaybank',
                'linkedin' => 'in/lauracunningham3',
            ]
        ],
    ];
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(new ContentLengthMiddleware());
$app->add(TwigMiddleware::createFromContainer($app));

$app->get('/donation', function (Request $request, Response $response, array $args): Response {
    $data = $request->getParsedBody();
    $donationAmount = $data['donation-amount'] ?? $data['donation-amount-other'];
    $charity = $data['charity-name'];

    return $this
        ->get('view')
        ->render(
            $response,
            'donation.html.twig',
            [
                'charity' => $charity,
                'donationAmount' => $donationAmount
            ]
        );
});

$app->post(
    '/create-charge',
    function (Request $request, Response $response, array $args): Response
    {
        Stripe::setApiKey(
            'sk_test_51LrH7iEXWvYZ89TYAzunk3OVbpjLXkSysDBngBSEJfVNFJIRi6fDQ4I98RNJSgXkaEU2HWhUv8UdRqQ0E40SnpBl00BVNBCnN0'
        );

        $jsonObj = json_decode($request->getBody()->getContents());
        $paymentIntent = PaymentIntent::create([
            'amount' => $jsonObj->amount,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'currency' => 'eur',
            'metadata' => [
                'charity' => $jsonObj->charity
            ]
        ]);

        return new JsonResponse([
            'clientSecret' => $paymentIntent->client_secret,
        ]);
    }
);

$app->get('/', function (Request $request, Response $response, array $args): Response {
    //$nonce = sha1(random_bytes(32));
    $data = [
        'charities' => $this->get('charities'),
        //'nonce' => $nonce,
    ];

    /*$response = $response->withHeader(
        "Content-Security-Policy",
        sprintf(
            "script-src 'self' js.stripe.com ajax.googleapis.com 'nonce-%s';",
            $nonce
        )
    );*/

    return $this->get('view')->render($response, 'default.html.twig', $data);
});

$app->get('/charities', function (Request $request, Response $response, array $args): Response {
    $charities = $this->get('charities');
    return new JsonResponse($charities);
});

/**
 * The "thank you" route, where the user is redirected to after they've submitted the form
 */
$app->get('/thank-you',
    function (Request $request, Response $response, array $args): Response
    {
        return $this->get('view')->render($response, 'thank-you.html.twig', []);
    }
);

$app->run();
