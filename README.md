# J-AI Chat Platform

J-AI is a chat platform developed by JadaDev! The AI models here are powered by Google's Gemini. J-AI is open source, making it accessible to everyone.

This project currently utilizes the Google Gemini API. Future development may include reworking the system to allow integration with other AI models and APIs.

## Features

*   **AI-Powered Chat:** Utilizes the Google Gemini API for intelligent chat interactions.
*   **Open Source:** Freely available and modifiable.
*   **Admin Panel:** Manage API keys and user roles.
*   **User Profile:** Users can manage their profile information (currently basic).

## Screenshots

### Chat Panel
![Chat Panel](https://raw.githubusercontent.com/JadaDev/J-AI-Chat-Platform/refs/heads/main/J-AI-Chat-Panel.png)

### Admin Panel
![Admin Panel](https://raw.githubusercontent.com/JadaDev/J-AI-Chat-Platform/refs/heads/main/J-AI-Admin-Panel.png)

### Profile Panel
![Profile Panel](https://raw.githubusercontent.com/JadaDev/J-AI-Chat-Platform/refs/heads/main/J-AI-Profile-Panel.png)

**Note:**  Make sure to create a `screenshots` folder in your repository and place the actual screenshot images there.  Name them `chat_panel.png`, `admin_panel.png`, and `profile_panel.png` respectively.  Otherwise, the images won't display correctly on GitHub.  You can use other image formats like `.jpg` or `.jpeg` if you prefer, but make sure to update the file extensions in the `README.md` file accordingly.

## Installation

1.  **Requirements:**
    *   PHP 7.x or higher
    *   MariaDB 10.x or MySQL 5.7 or higher
    *   XAMPP (recommended for local development) or a similar web server environment.

2.  **Configuration:**
    *   **Configure `config.php`:**  Edit the `config.php` file with your database connection details (host, username, password, database name).
    *   **Create Database:** Create a new database in MariaDB or MySQL.
    *   **Import SQL:** Import the `J-AI Chat Platform.sql` file into the database. This will create the necessary tables.

3.  **Setup:**
    *   Place the J-AI Chat Platform files in your web server's document root (e.g., `htdocs` in XAMPP).

4.  **Admin Setup:**
    *   **Create User Account:** Create a new user account through the registration page.
    *   **Set Admin Role:**  Access your database (e.g., using phpMyAdmin). In the `users` table, find the newly created user and change the `user_role` field to `admin`.
    *   **Login:** Log in to the platform with the newly created admin account.

5.  **API Key Configuration:**
    *   Access the Admin Panel.
    *   Add your Google Gemini API key in the designated field.

## Usage

*   **Chat Panel:**  Users can start chatting with the AI by typing their messages in the chat panel.
*   **Admin Panel:**  Administrators can manage API keys and user roles.  More features will be added to the admin panel in future updates.
*   **Profile Panel:** Users can view and edit (currently limited) their profile information.

## Contributing

J-AI is an open-source project, and contributions are welcome! Please feel free to fork the repository, make changes, and submit pull requests.

## License

[MIT License](LICENSE)

## Contact

Developed by JadaDev.  Feel free to reach out with any questions or suggestions.
