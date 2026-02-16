A laravel app for generating API's related to a transit agency. The agency in this case is Kochi Metro Rail Ltd which operates in the city of Ernakulam.</br>. This app uses the JSON files generated using scripts in https://github.com/shonphilip7/sample-GTFS-scripts repo for the API
##Prerequisites
1. Git </br>
2. Docker </br>
3. The JSON directory generated in https://github.com/shonphilip7/sample-GTFS-scripts repo. </br> 
##Steps to run locally
1. git clone git clone https://github.com/shonphilip7/transit-app-api.git </br>
2. cd transit-app-api </br>
3. docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install --ignore-platform-reqs. (This command runs the composer install on the laravel SAIL container) </br>
4.cp .env.example .env (Make sure .env is configured for SAIL-mysql and SAIL-redis) </br>
5. ./vendor/bin/sail up -d </br>
6. ./vendor/bin/sail artisan key:generate </br>
7. ./vendor/bin/sail artisan migrate </br>
8. From the JSON directory copy 'calendar.json' to storage/app/public/ </br>
9. From the JSON directory copy 'schedules' sub-directory to storage/app/public/ </br>
At this point the api is available at http://localhost/api/trainview/VYTA where 'VYTA' is the stop_id for 'Vytilla' stop. The API shows the arrival time for both direction for the stop. When  the app is not in use stop the SAIL containers by running ./vendor/bin/sail down  
