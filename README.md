# Heavy decoupling POC
## Overview

This project is a proof-of-concept (POC) that demonstrates how to integrate a Laravel backend with RabbitMQ and a Python consumer for asynchronous processing. The system simulates a scenario where a user uploads images as part of an exam. When 10 images are uploaded, Laravel queues a job in RabbitMQ for image processing (simulated in Python). Once the Python service finishes processing the images, it sends a callback notification to Laravel to log that the job has been completed.

## Why This Project?

This project was created to:
- **Decouple heavy processing tasks:** Offload resource-intensive operations (like image processing) from the main Laravel application.
- **Improve scalability:** Use RabbitMQ as a message broker to queue jobs and process them asynchronously.
- **Demonstrate inter-service communication:** Show how a PHP (Laravel) service can interact with a Python service through RabbitMQ and HTTP callbacks.
- **Provide a foundation:** Serve as a starting point for more complex microservice architectures involving different technology stacks.

## Architecture Overview

- **Laravel Backend:**  
  Handles user interactions (image uploads and exam management), stores exam data, and sends a job to RabbitMQ when an exam is complete.
  
- **RabbitMQ:**  
  Acts as the message broker, queuing exam processing jobs.
  
- **Python Consumer:**  
  Consumes jobs from RabbitMQ, simulates image processing, and sends a callback to Laravel upon completion.
  
- **Simple Frontend:**  
  A basic HTML/JavaScript interface that allows a user to upload images and finish an exam.

## Prerequisites

Before getting started, ensure you have the following installed:
- **Docker:** To run RabbitMQ
- **PHP & Composer:** For setting up the Laravel backend
- **Python 3 & pip:** For running the Python consumer
- **Git (optional):** To clone the repository

## Installation and Setup

### 1. Setup RabbitMQ

Run RabbitMQ in a Docker container with the management plugin:
```bash
docker run -d --hostname my-rabbit --name some-rabbit -p 5672:5672 -p 15672:15672 rabbitmq:3-management
```
Access the RabbitMQ management UI at [http://localhost:15672](http://localhost:15672) (Username: `guest`, Password: `guest`).

### 2. Setup Laravel

1. **Clone the Repository or Create a New Laravel Project:**

   ```bash
   git clone https://your-repo-url/laravel-rabbitmq-poc.git
   cd laravel-rabbitmq-poc
   ```
   Or create a new project:
   ```bash
   composer create-project laravel/laravel laravel-rabbitmq-poc
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

   Make sure Python 3 is installed and run:
   ```bash
   pip install pika requests
   ```



### 4. Setup the Frontend

1. **Place the Frontend File:**

   Copy the provided `index.html` file (containing the upload and finish exam forms) into the Laravel `public` folder.

2. **Access the Frontend:**

   Start the Laravel server:
   ```bash
   php artisan serve
   ```
   Open your browser at [http://localhost:8000/index.html](http://localhost:8000/index.html) to use the frontend.

## Using the Application

1. **Upload Images:**
   - Use the "Upload Image" form in the frontend.
   - Enter a User ID and optionally an Exam ID (if you are continuing an existing exam).
   - Select an image file and submit.  
     *Laravel saves the image, creates/updates the exam record, and returns the current exam ID and image count.*

2. **Finish Exam:**
   - Once at least 10 images are uploaded, use the "Finish Exam" form.
   - Enter the Exam ID and submit.  
     *Laravel verifies the image count and sends a job to RabbitMQ.*
     
3. **Processing and Notification:**
   - The Python consumer picks up the RabbitMQ job, simulates processing of the images, and sends an HTTP callback to Laravel’s `/api/job-complete` endpoint.
   - Laravel logs the notification (check `storage/logs/laravel.log`).

## Explanation of Key Functions

### Laravel Functions

- **uploadImage(Request $request):**
  - **Purpose:** Validates and processes image uploads.
  - **Process:**  
    - If no `exam_id` is provided, creates a new exam record.
    - Saves the uploaded image to the storage disk.
    - Updates the exam record with the new image path.
  - **Route:** `/api/upload-image`

- **finishExam(Request $request):**
  - **Purpose:** Finalizes an exam by sending a RabbitMQ job if at least 10 images have been uploaded.
  - **Process:**  
    - Verifies the exam has enough images.
    - Publishes a job to the `exam_jobs` queue with exam details.
  - **Route:** `/api/finish-exam`

- **jobComplete(Request $request):**
  - **Purpose:** Receives a callback notification from the Python consumer once the job is processed.
  - **Process:**  
    - Validates the payload and logs a notification message.
  - **Route:** `/api/job-complete`

### Python Consumer Functions

- **callback(ch, method, properties, body):**
  - **Purpose:** Processes a job received from RabbitMQ.
  - **Process:**  
    - Parses the job data.
    - Simulates image processing with delays.
    - Sends a notification callback to Laravel.
    - Acknowledges the message to remove it from the queue.

- **main():**
  - **Purpose:** Sets up the connection to RabbitMQ and starts the consumer.
  - **Process:**  
    - Connects to RabbitMQ.
    - Declares the queue and sets prefetch options.
    - Starts consuming messages, triggering the callback for each job.

## Troubleshooting and Notes

- **RabbitMQ:**  
  Ensure RabbitMQ is running on `localhost` with ports `5672` (for messaging) and `15672` (for the management UI).

- **API Endpoints:**  
  If your Laravel server runs on a different host or port, update the URL in the Python consumer and frontend accordingly.

- **File Permissions:**  
  Make sure Laravel’s storage folder is writable if you are storing images locally.

## Conclusion

This POC demonstrates a decoupled, asynchronous architecture where Laravel, RabbitMQ, and a Python consumer work together to handle heavy image processing tasks without blocking the main application. It provides a solid foundation for building scalable systems that integrate multiple technology stacks.

Happy coding!
