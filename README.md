# The Gift of Giving PHP Backend

This is the backend code behind my upcoming Twilio tutorial showing how to build a small site to help people make donations to worthwhile charities.

## Requirements

- [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos) installed globally
- [Docker Engine](https://docs.docker.com/engine/install/) or [Docker Desktop](https://www.docker.com/products/docker-desktop/), and [Docker Compose v2](https://docs.docker.com/compose/compose-v2/)
- [PHP](https://www.php.net/) 7.4 or above
- [curl](https://curl.se/)

## Usage

To use the application, run the commands below: 

```bash
# Clone the repository locally
git clone git@github.com:settermjd/gift-of-giving-backend.git

# Change into the cloned directory
cd gift-of-giving-backend

# Install the third-party dependencies
composer install

# Start the application using Docker Compose, by running the command below.
docker compose up -d
```
  
To check that the API is working, make a curl request to http://localhost:8080/charities.
If working properly, you will get a JSON response of all the charities in the system printed to the terminal.

Then, go and set up [the front end](https://github.com/settermjd/gift-of-giving-frontend).
