<p>A laravel app for generating API's related to a transit agency. The agency in this case is Kochi Metro Rail Ltd which operates in the city of Ernakulam. This app uses the JSON files generated using scripts in https://github.com/shonphilip7/sample-GTFS-scripts repo for the API </p>

## Prerequisites
1. Git 
2. Docker 
3. Files generated from the https://github.com/shonphilip7/sample-GTFS-scripts repo.

## Steps to run locally
1. git clone git clone https://github.com/shonphilip7/transit-app-api.git
2. cd transit-app-api
3. docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install --ignore-platform-reqs. (This command runs the composer install on the laravel SAIL container)
4. cp .env.example .env (Make sure .env is configured for SAIL-mysql and SAIL-redis)
5. ./vendor/bin/sail up -d </br>
6. ./vendor/bin/sail artisan key:generate
7. ./vendor/bin/sail artisan migrate
8. From the files genereated in repo mentioned in prerequisites copy 'calendar.json', schedules sub-directory and KML directory to storage/app/public/
<p>
    At this point the api's are available at: </br> 
    1. http://localhost/api/trainview/VYTA where 'VYTA' is the stop_id for 'Vytilla' stop. The API shows the arrival time for both direction for the stop.</br>
    2. http://localhost/api/kml/R1/0 where R1 is the route_id,0 is the direction_id. The API shows the route path (lat,lon)</br>
</p>   
<p>When  the app is not in use stop the SAIL containers by running ./vendor/bin/sail down </p>
