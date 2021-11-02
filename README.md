# WOBOT

WOBOT is the Workshop Orange CLI bot for standardising and automating repeated tasks, tests, and processes for Workshop Orange. WOBOT is build using the excellent [laravel-zero.com](https://laravel-zero.com/)

## Installation

`composer global require workshop-orange/wobot`

## Troubleshooting
Every now and then the build process overwrites the box.json - if you get a strange error that bootstrap/app.php is not in the phar, check box.json and reset its contents back to what is was for a successful build


## Maintenance notes
- bootstrap/app.php is modified to allow for reading a .env in the working dir of the app (not just the applications install directory).