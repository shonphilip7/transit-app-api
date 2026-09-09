# Transit Backend API
A starter laravel app for generating API's related to a transit agency. The agency in this case is <a href="https://kochimetro.org/open-data/">Kochi Metro Rail Ltd</a> which operates in the city of Ernakulam. This app uses the JSON files generated using scripts in https://github.com/shonphilip7/sample-GTFS-scripts repo for the API. This repository serves as the core RESTful API backend for the <a href="https://github.com/shonphilip7/transit-app-frontend">**Ionic-Angular**</a> mobile application. Built purely as a headless API service, it handles business logic, authentication, data persistence, and external integrations without serving any traditional frontend views.

## Key Architectural Notes
* **Stateless API:** Operates fully statelessly via API routes (`routes/api.php`).
* **CORS Configured:** Pre-configured Cross-Origin Resource Sharing (CORS) to securely communicate with the Ionic local development environment and deployed mobile apps.
* **No Frontend Scaffolding:** Contains no Blade views, Vite, or Mix configurations. Frontend assets are managed entirely within the separate mobile repository.

## Tech Stack & Requirements
- **Backend Framework:** Laravel 12.x (API Only)
- **Containerization:** Laravel Sail (Docker)
- **Caching/Queue:** Redis (via Sail Docker container)
- **Minimum Requirements:** Docker installed on your host machine

## Local Installation & Setup (Using Sail)
Because this project uses Laravel Sail, you do not need to install PHP, Composer, or a database engine locally on your machine. Everything runs securely inside Docker containers.

### 1. Clone the Repository
```bash
git clone https://github.com/shonphilip7/transit-app-api.git
cd transit-app-api
```
### 2. Install Composer Dependencies via Docker
Because this is a fresh clone, the project does not have a `vendor` folder or a `sail` script yet. If you do not have PHP or Composer installed on your local computer, run this temporary Docker command to pull down the correct PHP version and install your dependencies:
```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs
```
* **What this does:** It spins up a temporary, lightweight container running PHP 8.3, uses it to securely install all the Laravel package dependencies into your project folder, and then deletes itself automatically (`--rm`).
- docker run --rm: Creates a temporary container and automatically removes it once the execution finishes.
- -u "$(id -u):$(id -g)": Runs the container under your host user's IDs so that generated files like the vendor folder do not end up with root-only permissions.
- -v "$(pwd):/var/www/html": Mounts your current local project directory into the container.
- -w /var/www/html: Sets the working directory inside the container.
- laravelsail/php83-composer:latest: Uses the official lightweight Composer-ready PHP image provided by Laravel Sail.
- composer install: Installs all required packages defined in your composer.json, including Sail itself.
- --ignore-platform-reqs: Tells Composer to ignore platform requirements
### 3. Environment Configuration
Copy the template environment file:
```bash
cp .env.example .env
```
Open the newly created `.env` file and update both the **Database** and **Redis** sections to ensure it plays nicely with Laravel Sail's Docker network:
```
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql         # Crucial for Sail: must match the Docker service name, not 127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel 
DB_USERNAME=sail
DB_PASSWORD=password

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=redis      # Crucial for Sail: must match the Docker service name, not 127.0.0.1
REDIS_PORT=6379
```
### 4. Start the Application (Sail)
Start the Docker containers in the background:
```bash
./vendor/bin/sail up -d
```
### 5. Initialize the Application
Once the containers are up and running, generate your app key and run the database migrations:
```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```
### 6. Copy Internal Backend Assets
Copy the project's default asset files to the internal application storage directory. These files are used strictly for backend processing:
```bash
cp -r public/assets/. storage/app/public/
```
**Storage Architecture Note:** In a full production environment, these assets are architected to live inside an **AWS S3 Bucket**. As a local development workaround, the application is currently configured to read from the local file system (`storage/app/public/`).

## Stopping the Application
When you are done developing, you can securely shut down the Docker containers and free up system resources by running:
```bash
./vendor/bin/sail down
```
* **Note:** This stops the containers but keeps your database volumes safe. Your data will still be there the next time you boot up Sail!

## Transit API Endpoints (No Authorization Required)
These endpoints can be accessed publicly without a bearer token or session cookie, exposing core schedule metrics to the Ionic client.

### Base URL
```text
http://localhost/api
```

### Endpoint Details
#### Get Scheduled Arrival Times
Retrieves official scheduled arrival times for a specific transit route at a designated stop.
* **URL Structure:** `/trainview/{route_id}/{stop_id}`
* **Method:** `GET`
* **Headers:** `Accept: application/json`
**Business Logic Note:** This endpoint queries schedule tables dynamically based on the current server timestamp. It filters records to return present and upcoming future scheduled trips; past trips are automatically excluded from the payload.

**Path Parameters:**
| Parameter | Type | Required | Description | Example |
| :--- | :--- | :--- | :--- | :--- |
| `route_id` | `string` | **Yes** | The unique identifier for the transit line. | `R1` |
| `stop_id` | `string` | **Yes** | The unique identifier for the transit stop or station. | `VYTA` |
**Example Request URL:**
```text
http://localhost/api/trainview/R1/VYTA
```
#### List All Routes
Retrieves a complete list of all available transit routes and lines managed by the agency.
* **URL Structure:** `/routes`
* **Method:** `GET`
* **Headers:** `Accept: application/json`
* **Path Parameters:** None
**Example Request URL:**
```text
http://localhost/api/routes/
```
#### List Stops by Route
Retrieves a complete list of all transit stops or stations associated with a specific route.
* **URL Structure:** `/{route_id}/stops`
* **Method:** `GET`
* **Headers:** `Accept: application/json`

**Path Parameters:**
| Parameter | Type | Required | Description | Example |
| :--- | :--- | :--- | :--- | :--- |
| `route_id` | `string` | **Yes** | The unique identifier for the transit line. | `R1` |
**Example Request URL:**
```text
http://localhost/api/R1/stops
```
#### Get Route Geometry (Coordinates)
Retrieves a sequential list of geographic coordinates (latitude, longitude, and altitude) used to plot the visual path of a transit route on a map.
* **URL Structure:** `/kml/{route_id}/{direction}`
* **Method:** `GET`
* **Headers:** `Accept: application/json`

**Path Parameters:**
| Parameter | Type | Required | Description | Example |
| :--- | :--- | :--- | :--- | :--- |
| `route_id` | `string` | **Yes** | The unique identifier for the transit line. | `R1` |
| `direction` | `integer` (`0` or `1`) | **Yes** | The transit heading. Use `1` for **inbound** and `0` for **outbound**. | `1` |
**Example Request URL:**
```text
http://localhost/api/kml/R1/0
```

## User Authentication Endpoints (Unprotected)
These endpoints handle user account creation and authentication. They do not require an authorization token to access.

### Endpoint Details

#### User Registration
Creates a new user account within the system.
* **URL Structure:** `/register`
* **Method:** `POST`
* **Headers:** 
    * `Accept: application/json`
    * `Content-Type: application/json`

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
| :--- | :--- | :--- | :--- | :--- |
| `name` | `string` | **Yes** | The user's full name. | `Jane Doe` |
| `email` | `string` | **Yes** | A unique, valid email address. | `jane@example.com` |
| `password` | `string` | **Yes** | The account password. | `securepassword123` |
**Example Request Payload:**
```json
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "securepassword123"
}
```
#### User Login
Authenticates an existing user and returns an access token.
* **URL Structure:** `/login`
* **Method:** `POST`
* **Headers:** 
    * `Accept: application/json`
    * `Content-Type: application/json`

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
| :--- | :--- | :--- | :--- | :--- |
| `email` | `string` | **Yes** | A unique, valid email address. | `jane@example.com` |
| `password` | `string` | **Yes** | The account password. | `securepassword123` |
**Example Request Payload:**
```json
{
    "email": "jane@example.com",
    "password": "securepassword123"
}
```

## Protected API Endpoints (Requires Authentication)
These endpoints require a valid access token to be passed in the HTTP request headers. If the token is missing, invalid, or expired, the API will return a `401 Unauthorized` response.

### Required Header
All requests to protected routes must include the following header:
```text
Authorization: Bearer <your_access_token>
```

### Endpoint Details

#### User Logout
Invalidates and revokes the authenticated user's current API access token, securely destroying the session.
* **URL Structure:** `/logout`
* **Method:** `POST`
* **Headers:** 
    * `Accept: application/json`
    * `Authorization: Bearer <token>`

**Request Body:** None \
**Behavior:** Upon a successful request, Laravel Sanctum will delete the current token record from the database. The Ionic app should then delete the token from its local storage and redirect the user to the login screen.
