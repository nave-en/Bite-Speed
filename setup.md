Requirements
   PHP version - ^8.1
   Laravel version - ^10.10


For Ubuntu
   -Install PHP
   -Install MySQL
   -Install composer
   -Install Laravel using composer


For windows
   - Install Xampp
   - Install composer
   - Install Laravel via composer


Once the installation is successfully completed, create a db in your MySQL database.
Then create a file named as ".env" in the folder and update the db configuration details like username and password.
Then give the permission to read and write for storage folder.
Then Run the migrations using the command
   - PHP artisan migrate
You will see a new table "contacts" is added in the database
For running the server, use
   - php artisan serve

Test the application use the PostMAN
url -> http://127.0.0.1:8000/identity
data ->
{
	"email": "b@gmail.com",
	"phoneNumber": "21"
}
Note : The local host url may vary, for local host url run the php artisan command you can get your url and then add the route