# BathHack2025 Project

This project is a web application that integrates a Spotify player with mood-based themes.

## Prerequisites

Ensure you have the following installed on your system:

1. **PHP**: Version 8.0 or higher.
2. **Node.js and npm**: Install the latest stable version from [Node.js](https://nodejs.org/).
3. **Composer**: Install Composer from [getcomposer.org](https://getcomposer.org/).

### Installing PHP

- **Windows**:  
  Download PHP from [windows.php.net](https://windows.php.net/download/) and follow the installation instructions. Add PHP to your system's PATH environment variable.

- **macOS**:  
  Use Homebrew to install PHP:

  ```bash
  brew install php
  ```

- **Linux**:  
  Use your package manager to install PHP. For example, on fedora:
  ```bash
  sudo dnf update
  sudo dnf install php
  ```

### Installing Node.js and npm

- **Windows**:  
  Download the Node.js installer from [nodejs.org](https://nodejs.org/) and follow the installation instructions.

- **macOS**:  
  Use Homebrew to install Node.js:

  ```bash
  brew install node
  ```

- **Linux**:  
  Use your package manager to install Node.js. For example, on Ubuntu:
  ```bash
  sudo apt update
  sudo apt install nodejs npm
  ```

### Installing Composer

- **Windows**:  
  Download and run the Composer-Setup.exe from [getcomposer.org](https://getcomposer.org/).

- **macOS/Linux**:  
  Run the following command:
  ```bash
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  ```

## Installation

Follow these steps to set up the project:

### Backend Setup

1. Navigate to the backend directory:
   ```bash
   cd backend
   ```
2. Install PHP dependencies using Composer:
   ```bash
   composer install
   ```

### Frontend Setup

1. Navigate to the frontend directory:
   ```bash
   cd ../frontend
   ```
2. Install npm dependencies:
   ```bash
   npm install
   ```

## Running the Project

To deploy and run the project, use the following command in the `frontend` directory:

```bash
npm run deploy
```

This will build and serve the application.

## Notes

- Ensure your backend server is running and accessible at `http://127.0.0.1:8000` for API requests.
- For Spotify integration, ensure you have valid credentials and the backend is configured to handle Spotify API requests.
