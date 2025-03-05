# Heavy Decoupling POC

## Overview

This project is a proof-of-concept (POC) that demonstrates how to integrate a Laravel backend with RabbitMQ and a Python consumer for asynchronous processing. The system simulates a scenario where a user uploads images as part of an exam. Once at least 10 images are uploaded, Laravel queues a job in RabbitMQ for image processing (simulated in Python). When the Python service finishes processing the images, it sends a callback notification to Laravel, which then logs that the job has been completed.

## Why This Project?

This project was created to:
- **Decouple heavy processing tasks:** Offload resource-intensive operations (such as image processing) from the main Laravel application.
- **Improve scalability:** Use RabbitMQ as a message broker to queue jobs and process them asynchronously.
- **Demonstrate inter-service communication:** Show how a PHP (Laravel) service can interact with a Python service through RabbitMQ and HTTP callbacks.
- **Provide a foundation:** Serve as a starting point for more complex microservice architectures involving multiple technology stacks.

## Architecture Overview

- **Laravel Backend:**  
  Handles user interactions (image uploads and exam management), stores exam data, and sends a job to RabbitMQ when an exam is complete.
  
- **RabbitMQ:**  
  Acts as the message broker, queuing exam processing jobs.
  
- **Python Consumer:**  
  Consumes jobs from RabbitMQ, processes the images (simulated by delays), and sends an HTTP callback to Laravel upon completion. The consumer spawns a new thread for each message and sets a higher prefetch count (3) to allow concurrent processing.
  
- **Simple Frontend:**  
  A basic HTML/JavaScript interface that allows a user to upload multiple images (up to 10 at a time) and finish an exam.

## Prerequisites

Before getting started, ensure you have the following installed:
- **Docker:** To run RabbitMQ.
- **PHP & Composer:** For setting up the Laravel backend.
- **Python 3 & pip:** For running the Python consumer.
- **Git (optional):** To clone the repository.

## Installation and Setup

### 1. Setup RabbitMQ

Run RabbitMQ in a Docker container with the management plugin:

```bash
docker run -d --hostname my-rabbit --name some-rabbit -p 5672:5672 -p 15672:15672 rabbitmq:3-management
```

Access the RabbitMQ management UI at [http://localhost:15672](http://localhost:15672) (Username: `guest`, Password: `guest`).

### 2. Setup Laravel

1. **Clone the Repository:**

   ```bash
   git clone https://github.com/ZodicSlanser/Heavy-decoupling-POC
   cd laravel-rabbitmq-poc
   ```
   
2. **Install Dependencies:**

   ```bash
   composer install
   ```

3. **Configure the Database:**

   Edit your `.env` file to configure your database connection (MySQL, SQLite, etc.).

4. **Run Migrations:**

   A migration has been set up to create an `exams` table. Run:

   ```bash
   php artisan migrate
   ```

5. **Install the RabbitMQ PHP Library:**

   ```bash
   composer require php-amqplib/php-amqplib
   ```

6. **Create a Storage Link (if using local disk storage):**

   ```bash
   php artisan storage:link
   ```

### 3. Setup Python Consumer

1. **Install Python Dependencies:**

   Ensure Python 3 is installed, then run:

   ```bash
   pip install pika requests
   ```

2. **Python Consumer Script:**

   The consumer script (see `consumer.py`) now uses threading to process each message concurrently. It increases the prefetch count to 3 so that up to three messages can be delivered concurrently. The script processes each image with a simulated delay, sends a callback notification to Laravel, and acknowledges the message using `add_callback_threadsafe()`.

### 4. Access the Frontend

1. **Place the Frontend File:**

   A simple `index.html` file is provided in the `public` folder. This file supports multiple image uploads (up to 10 at a time).

2. **Start the Laravel Server:**

   ```bash
   php artisan serve
   ```

3. **Open your Browser:**

   Visit [http://localhost:8000/index.html](http://localhost:8000/index.html) to use the frontend.

## Using the Application

1. **Upload Images:**
   - Use the "Upload Images" form in the frontend.
   - Enter a User ID and optionally an Exam ID (if you are continuing an existing exam).
   - Select multiple image files (up to 10 at once) and submit.  
     *Laravel saves the images, creates/updates the exam record, and returns the current exam ID along with the count and paths of uploaded images.*

2. **Finish Exam:**
   - Once at least 10 images have been uploaded, use the "Finish Exam" form.
   - Enter the Exam ID and submit.  
     *Laravel verifies that the exam has enough images and sends a job to RabbitMQ.*

3. **Processing and Notification:**
   - The Python consumer picks up the RabbitMQ job, processes the images (with each image simulated by a delay), and sends an HTTP callback to Laravel’s `/api/job-complete` endpoint.
   - Laravel logs the notification (check `storage/logs/laravel.log`).

## Explanation of Key Functions

### Laravel Functions

- **uploadImage(Request $request):**
  - **Purpose:** Validates and processes multiple image uploads.
  - **Process:**  
    - If no `exam_id` is provided, creates a new exam record.
    - Saves the uploaded images to the storage disk.
    - Uses the `Exam` model’s `addImage` method to append each image path to the exam record.
  - **Route:** `/api/upload-image`

- **finishExam(Request $request):**
  - **Purpose:** Finalizes an exam by sending a RabbitMQ job if at least 10 images have been uploaded.
  - **Process:**  
    - Checks if the exam has enough images using the `hasEnoughImages` method.
    - Publishes a job to the `exam_jobs` queue with exam details (including exam ID, user ID, and image paths).
  - **Route:** `/api/finish-exam`

- **jobComplete(Request $request):**
  - **Purpose:** Receives a callback notification from the Python consumer once the job is processed.
  - **Process:**  
    - Validates the incoming payload and logs the notification message.
  - **Route:** `/api/job-complete`

- **Exam Model Methods:**
  - **addImage(string $path):**
    - Appends an image path to the exam’s `images` array and saves the record.
  - **hasEnoughImages(int $minImages = 10):**
    - Checks if the exam has at least the minimum required number of images (default is 10).

### Python Consumer Functions

- **callback(ch, method, properties, body):**
  - **Purpose:** For every message received from RabbitMQ, spins up a new thread to process the message concurrently.
  - **Process:**  
    - Spawns a new thread that calls `process_message`.

- **process_message(ch, method, properties, body):**
  - **Purpose:** Processes a single RabbitMQ message.
  - **Process:**  
    - Parses the JSON data from the message.
    - Simulates processing each image (with a delay for each image).
    - Sends an HTTP POST request to Laravel’s `/api/job-complete` endpoint to notify that processing is complete.
    - Uses `add_callback_threadsafe()` to acknowledge the message safely on the main thread.

- **main():**
  - **Purpose:** Sets up the connection to RabbitMQ and starts consuming messages.
  - **Process:**  
    - Establishes a connection, declares the durable queue, sets a `prefetch_count` of 3 (to allow multiple concurrent messages), and begins consuming messages.

## Troubleshooting and Notes

- **RabbitMQ:**  
  Ensure RabbitMQ is running on `localhost` with ports `5672` (messaging) and `15672` (management UI).

- **API Endpoints:**  
  If your Laravel server runs on a different host or port, update the URLs in the Python consumer and frontend accordingly.

- **File Permissions:**  
  Ensure Laravel’s storage folder is writable if you are storing images locally.
---

