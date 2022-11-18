# Twilio Halloween Trick-or-Treat Image Sender

This is the code for my upcoming Twilio tutorial showing how to build a Halloween-themed Trick-or-Treat SMS image sender.

## Requirements

- PHP 7.4 or above
- A free [Twilio](https://www.twilio.com/) account
- An account with a file hosting service.

## Usage

To use the application: 

- Clone the repository locally
- Copy _.env.example_ as _.env_ 
- Retrieve your Twilio account SID, Auth Token, and phone number from the [Twilio Console](https://www.twilio.com/console/) and paste them into .env as the values for the first three variables (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, & `TWILIO_PHONE_NUMBER`).
- Upload one or more images to your file hosting service of choice, such as [DigitalOcean Spaces](https://www.digitalocean.com/products/spaces), then set the base URL to the images as the value of `IMAGE_URL_BASE` in _.env_.
- Start the application using PHP's built-in web server, by running the command below.
  ```bash
  php -S 0.0.0.0:8080 -t public &
  ```
- Then, open http://localhost:8080 in your browser of choice.
