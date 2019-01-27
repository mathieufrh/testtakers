## implementation

This project has been implemented as if it was part of a bigger, real life, project.

Thus, the framework used may seem overkill for this application but it would certainly be fitted for a real project of this kind.

Regarding the data sources, two approaches can be considered:

#### Use different data sources format to feed our resources

This solution will use csv, json, mysql, etc... to directly create the resources.
You can switch the source from the `.env` file by setting `DATA_SOURCE` variable. More details on it in the `config/sources.php` configuration file.

To be able to filter the resources we must convert it to a convenient format first, otherwise we would have to reimplement every filtering function each time the source format changes.
In Laravel it's a good idea to convert the resources from any kind of sources to a Collection. Collections are enhanced PHP arrays.

I have created three models to illustrate the use of Collections with different types source formats, check `app` folder.

#### Use different data sources format to feed a database backend

This approach is the best approach in my opinion because it allows us to fetch the resources very effectively. It made it very easy to append relationships to model, select only some resources attributes, filter the resources based on relationships or attributes... You won't have to reimplement everything each time the data source changes.
But this requires to put all the resources in a database first so if this is not feasible, stick with the first approach. You can see how easy this can be by checking the database seeders using json or csv in `database/seeds`. After the database has been seeded we can use a single TestTaker model that extends Eloquent Model and it begins a piece of cake to filter, sort, include relationships, append custom attributes...

I keep in mind that for real project with huge data sources this is the best solution for performances and flexibility.

As an example, I add an example QueryBuilder class that would handle a GET request and use it to fiulter the requested resource. If you want to use it you can do something like the following in a generic controller:

```
use App\Http\Resources\ModelResource;
use App\Http\QueryBuilders\QueryBuilder;
use App\Http\Resources\ModelResourceCollection;

/**
 * Class ApiController
 *
 * It extends a generic Controller that should set a model protected variable.
 *
 * @package App\Http\Controllers\API
 */
class ApiController extends Controller
{
    /**
     * Get a listing of all the resources.
     *
     * @param  Request  $request
     * @return Response
     */
    public function fetchResourceCollection(Request $request)
    {
        $queryBuilder = new QueryBuilder($this->model, $request);

        return new ModelResourceCollection($queryBuilder->build()->get());
    }
}
```

The example QueryBuilder class is `app/Http/QueryBuilders/QueryBuilder.php and looks a bit messy as I don't have time to clean it up, but it gives an idea of a fully generic implementation for an API that can handles any kind of requests and return standardized JSON responses. The logic lies in the model where you set the attributes, relationships, custom "appendable" attributes, etc... This way, you'll never have to create a new filtering method for any of your new models, it already exists and works!

## Project scope considerations

As per the rules of this exercise I have to modify the way of the API should work:

* The JSON responses are not wrapped into a data key.
* The JSON responses have no meta data attached to them.
* The API version "v1" is hardcoded in the routes definitions.

## Improvements

Here is a list of things that would be worth adding to this project:

#### API authentication

This is done very easily using Laravel Passport.

Once the package is installed and the database has been migrated you can issue clients, secrets and tokens.

To secure a model with Passport authentication, you just have to add the `HasApiTokens` trait to it.

I used to implement OAuth2 in my APIs and this is done seamlessly.

#### API resources access restriction

We can restrict access to resources using the class Illuminate\Auth\Access\HandlesAuthorization.
An example implementation has been inserted in the project, but is not actually used. The policies are declared in `app/Policies/ModelPolicy.php`. All you have to do to use them is to uncomment the "authorize" lines in `app/Http/Controllers/Api/TestTakerApiController.php`.

#### Generic Api Resources

A generic resource would abstract every resource of the project. You can check a working version in `app/Http/Resources/Generic` that would handle requests like `/v1/testtakers?country=russia&is_admin=true&fields=id,created_at,firstname,lastname&includes=role,languages&orderBy=created_at,desc`

Of course I prefer to stick with the simle non-generic resources transformer in `app/Http/Resources/TestTaker.php` because it the TestTaker model is very simple and has no relationships etc...

#### Generic Controller

A generic controller can be implemented to handle every GET, POST, PUT, DELETE... HTTP requests and return standard JSON responses. No matter what the resource model is we can use a separate query builder to filter the resources using the request. See [the paragraph on generic controller][Use different data sources format to feed a database backend].

## Results

#### testtakers lists

![list](https://i.imgur.com/06Lyn7z.png "list")

#### testtaker details

![details](https://i.imgur.com/S66iyLG.png "details")

## Docker

You may use docker to build this application. It's very easy:

```
docker-compose build
docker-compose up
```
