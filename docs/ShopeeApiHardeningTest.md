debian@DESKTOP-PEAOSJ9:~/projects/marketplace-analytics$ ./vendor/bin/sail test tests/Feature/ShopeeApiHardeningTest.php

   FAIL  Tests\Feature\ShopeeApiHardeningTest
  ✓ incremental order sync merges into existing staging without duplicates                                                                   7.60s  
  ✓ partial order sync failure keeps previously staged rows                                                                                  0.04s  
  ✓ re promoting unchanged staging is skipped without duplicate rows                                                                         0.15s  
  ✓ changed staging after promotion re promotes and cleans removed line                                                                      0.09s  
  ✓ stale staging blocks promotion with zero writes                                                                                          0.05s  
  ⨯ fresh successful sync clears stale flag and unblocks promotion                                                                           0.07s  
  ⨯ every sync operation is audited without secrets                                                                                          0.03s  
  ⨯ failed sync is audited as failure                                                                                                        0.03s  
  ✓ authorize endpoint requires post                                                                                                         0.38s  
  ✓ connection endpoint requires post and uses csrf protected route                                                                          0.14s  
  ✓ production connection error does not expose remote exception details                                                                     0.03s  
  ✓ connection endpoint can be called with post                                                                                              0.03s  
  ⨯ production sync error does not expose remote exception details                                                                           0.03s  
  ✓ production safe state does not expose last sync error details                                                                            0.02s  
  ✓ clear removes staging and resets sync tracking                                                                                           0.03s  
  ✓ clear is tenant scoped                                                                                                                   0.04s  
  ⨯ sync routes reject overlapping sync for same account                                                                                     0.03s  
  ⨯ sync lock is scoped to account                                                                                                           0.03s  
  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ShopeeApiHardeningTest > fresh successful sync clears stale flag and unblocks promotion                                    
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Class "App\Http\Controllers\Cache" not found in /var/www/html/app/Http/Controllers/ShopeeApiController.php:330
Stack trace:
#0 /var/www/html/app/Http/Controllers/ShopeeApiController.php(263): App\Http\Controllers\ShopeeApiController->withSyncLock()
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\Http\Controllers\ShopeeApiController->syncOrders()
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch()
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(216): Illuminate\Routing\Route->runController()
#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(822): Illuminate\Routing\Route->run()
#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}()
#6 /var/www/html/app/Http/Middleware/EnsureAccountIsActive.php(16): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\EnsureAccountIsActive->handle()
#8 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware.php(147): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware->handle()
#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(168): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(129): Illuminate\Routing\Middleware\ThrottleRequests->handleRequest()
#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(90): Illuminate\Routing\Middleware\ThrottleRequests->handleRequestUsingNamedLimiter()
#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\ThrottleRequests->handle()
#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php(104): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestForgery->handle()
#20 /var/www/html/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#26 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#28 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#29 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#30 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#33 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#34 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#35 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}()
#36 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware/EnsureGetOnRedirect.php(19): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#37 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware\EnsureGetOnRedirect->handle()
#38 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#39 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#40 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#41 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#42 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#43 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#44 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#45 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#46 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#47 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#48 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#49 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#50 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#51 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#52 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#53 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#54 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#55 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#56 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#57 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#58 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#59 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle()
#60 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call()
#61 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json()
#62 /var/www/html/tests/Feature/ShopeeApiHardeningTest.php(412): Illuminate\Foundation\Testing\TestCase->postJson()
#63 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1318): Tests\Feature\ShopeeApiHardeningTest->test_fresh_successful_sync_clears_stale_flag_and_unblocks_promotion()
#64 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod()
#65 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(361): PHPUnit\Framework\TestRunner->run()
#68 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 /var/www/html/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 /var/www/html/vendor/phpunit/phpunit/src/TextUI/Application.php(229): PHPUnit\TextUI\TestRunner->run()
#71 /var/www/html/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#72 {main}

----------------------------------------------------------------------------------

Class "App\Http\Controllers\Cache" not found

  at tests/Feature/ShopeeApiHardeningTest.php:412
    408▕                 $this->orderRow($orderSn),
    409▕             ])),
    410▕         ]);
    411▕ 
  ➜ 412▕         $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-orders'), ['page_size' => 5])->assertOk();
    413▕ 
    414▕         $connection->refresh();
    415▕         $this->assertFalse($connection->isStagingStale());
    416▕ 

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ShopeeApiHardeningTest > every sync operation is audited without secrets                                                   
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Class "App\Http\Controllers\Cache" not found in /var/www/html/app/Http/Controllers/ShopeeApiController.php:330
Stack trace:
#0 /var/www/html/app/Http/Controllers/ShopeeApiController.php(263): App\Http\Controllers\ShopeeApiController->withSyncLock()
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\Http\Controllers\ShopeeApiController->syncOrders()
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch()
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(216): Illuminate\Routing\Route->runController()
#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(822): Illuminate\Routing\Route->run()
#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}()
#6 /var/www/html/app/Http/Middleware/EnsureAccountIsActive.php(16): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\EnsureAccountIsActive->handle()
#8 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware.php(147): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware->handle()
#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(168): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(129): Illuminate\Routing\Middleware\ThrottleRequests->handleRequest()
#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(90): Illuminate\Routing\Middleware\ThrottleRequests->handleRequestUsingNamedLimiter()
#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\ThrottleRequests->handle()
#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php(104): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestForgery->handle()
#20 /var/www/html/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#26 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#28 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#29 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#30 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#33 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#34 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#35 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}()
#36 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware/EnsureGetOnRedirect.php(19): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#37 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware\EnsureGetOnRedirect->handle()
#38 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#39 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#40 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#41 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#42 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#43 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#44 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#45 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#46 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#47 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#48 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#49 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#50 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#51 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#52 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#53 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#54 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#55 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#56 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#57 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#58 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#59 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle()
#60 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call()
#61 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json()
#62 /var/www/html/tests/Feature/ShopeeApiHardeningTest.php(442): Illuminate\Foundation\Testing\TestCase->postJson()
#63 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1318): Tests\Feature\ShopeeApiHardeningTest->test_every_sync_operation_is_audited_without_secrets()
#64 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod()
#65 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(361): PHPUnit\Framework\TestRunner->run()
#68 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 /var/www/html/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 /var/www/html/vendor/phpunit/phpunit/src/TextUI/Application.php(229): PHPUnit\TextUI\TestRunner->run()
#71 /var/www/html/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#72 {main}

----------------------------------------------------------------------------------

Class "App\Http\Controllers\Cache" not found

  at tests/Feature/ShopeeApiHardeningTest.php:442
    438▕                 $this->orderRow('220404NF3CFFNY'),
    439▕             ])),
    440▕         ]);
    441▕ 
  ➜ 442▕         $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-orders'), ['page_size' => 5])->assertOk();
    443▕ 
    444▕         $audit = AccountAuditLog::query()->where('action', 'shopee_api.sync.orders')->first();
    445▕         $this->assertNotNull($audit);
    446▕         $this->assertSame($user->id, $audit->user_id);

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ShopeeApiHardeningTest > failed sync is audited as failure                                                                 
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Class "App\Http\Controllers\Cache" not found in /var/www/html/app/Http/Controllers/ShopeeApiController.php:330
Stack trace:
#0 /var/www/html/app/Http/Controllers/ShopeeApiController.php(289): App\Http\Controllers\ShopeeApiController->withSyncLock()
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\Http\Controllers\ShopeeApiController->syncIncome()
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch()
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(216): Illuminate\Routing\Route->runController()
#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(822): Illuminate\Routing\Route->run()
#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}()
#6 /var/www/html/app/Http/Middleware/EnsureAccountIsActive.php(16): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\EnsureAccountIsActive->handle()
#8 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware.php(147): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware->handle()
#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(168): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(129): Illuminate\Routing\Middleware\ThrottleRequests->handleRequest()
#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(90): Illuminate\Routing\Middleware\ThrottleRequests->handleRequestUsingNamedLimiter()
#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\ThrottleRequests->handle()
#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php(104): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestForgery->handle()
#20 /var/www/html/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#26 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#28 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#29 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#30 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#33 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#34 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#35 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}()
#36 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware/EnsureGetOnRedirect.php(19): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#37 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware\EnsureGetOnRedirect->handle()
#38 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#39 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#40 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#41 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#42 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#43 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#44 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#45 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#46 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#47 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#48 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#49 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#50 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#51 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#52 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#53 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#54 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#55 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#56 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#57 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#58 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#59 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle()
#60 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call()
#61 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json()
#62 /var/www/html/tests/Feature/ShopeeApiHardeningTest.php(470): Illuminate\Foundation\Testing\TestCase->postJson()
#63 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1318): Tests\Feature\ShopeeApiHardeningTest->test_failed_sync_is_audited_as_failure()
#64 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod()
#65 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(361): PHPUnit\Framework\TestRunner->run()
#68 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 /var/www/html/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 /var/www/html/vendor/phpunit/phpunit/src/TextUI/Application.php(229): PHPUnit\TextUI\TestRunner->run()
#71 /var/www/html/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#72 {main}

----------------------------------------------------------------------------------

Class "App\Http\Controllers\Cache" not found

  at tests/Feature/ShopeeApiHardeningTest.php:471
    467▕                 ->push('', 429),
    468▕         ]);
    469▕ 
    470▕         $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-income'))
  ➜ 471▕             ->assertOk()
    472▕             ->assertJson(['ok' => false, 'rate_limited' => true]);
    473▕ 
    474▕         $audit = AccountAuditLog::query()->where('action', 'shopee_api.sync.income')->first();
    475▕         $this->assertNotNull($audit);

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ShopeeApiHardeningTest > production sync error does not expose remote exception details                                    
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Class "App\Http\Controllers\Cache" not found in /var/www/html/app/Http/Controllers/ShopeeApiController.php:330
Stack trace:
#0 /var/www/html/app/Http/Controllers/ShopeeApiController.php(289): App\Http\Controllers\ShopeeApiController->withSyncLock()
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\Http\Controllers\ShopeeApiController->syncIncome()
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch()
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(216): Illuminate\Routing\Route->runController()
#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(822): Illuminate\Routing\Route->run()
#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}()
#6 /var/www/html/app/Http/Middleware/EnsureAccountIsActive.php(16): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\EnsureAccountIsActive->handle()
#8 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware.php(147): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware->handle()
#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(168): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(129): Illuminate\Routing\Middleware\ThrottleRequests->handleRequest()
#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(90): Illuminate\Routing\Middleware\ThrottleRequests->handleRequestUsingNamedLimiter()
#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\ThrottleRequests->handle()
#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php(104): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestForgery->handle()
#20 /var/www/html/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#26 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#28 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#29 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#30 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#33 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#34 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#35 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}()
#36 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware/EnsureGetOnRedirect.php(19): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#37 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware\EnsureGetOnRedirect->handle()
#38 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#39 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#40 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#41 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#42 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#43 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#44 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#45 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#46 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#47 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#48 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#49 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#50 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#51 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#52 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#53 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#54 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#55 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#56 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#57 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#58 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#59 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle()
#60 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call()
#61 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json()
#62 /var/www/html/tests/Feature/ShopeeApiHardeningTest.php(579): Illuminate\Foundation\Testing\TestCase->postJson()
#63 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1318): Tests\Feature\ShopeeApiHardeningTest->test_production_sync_error_does_not_expose_remote_exception_details()
#64 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod()
#65 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(361): PHPUnit\Framework\TestRunner->run()
#68 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 /var/www/html/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 /var/www/html/vendor/phpunit/phpunit/src/TextUI/Application.php(229): PHPUnit\TextUI\TestRunner->run()
#71 /var/www/html/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#72 {main}

----------------------------------------------------------------------------------

Class "App\Http\Controllers\Cache" not found

  at tests/Feature/ShopeeApiHardeningTest.php:580
    576▕             ]), 500),
    577▕         ]);
    578▕ 
    579▕         $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-income'))
  ➜ 580▕             ->assertOk()
    581▕             ->json();
    582▕ 
    583▕         $this->assertFalse($response['ok']);
    584▕         $this->assertSame('Shopee API request failed. Please try again later.', $response['error']);

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ShopeeApiHardeningTest > sync routes reject overlapping sync for same account                                              
  Expected response status code [409] but received 500.
Failed asserting that 500 is identical to 409.

The following exception occurred during the last request:

Error: Class "App\Http\Controllers\Cache" not found in /var/www/html/app/Http/Controllers/ShopeeApiController.php:330
Stack trace:
#0 /var/www/html/app/Http/Controllers/ShopeeApiController.php(263): App\Http\Controllers\ShopeeApiController->withSyncLock()
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\Http\Controllers\ShopeeApiController->syncOrders()
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch()
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(216): Illuminate\Routing\Route->runController()
#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(822): Illuminate\Routing\Route->run()
#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}()
#6 /var/www/html/app/Http/Middleware/EnsureAccountIsActive.php(16): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\EnsureAccountIsActive->handle()
#8 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware.php(147): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware->handle()
#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(168): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(129): Illuminate\Routing\Middleware\ThrottleRequests->handleRequest()
#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(90): Illuminate\Routing\Middleware\ThrottleRequests->handleRequestUsingNamedLimiter()
#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\ThrottleRequests->handle()
#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php(104): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestForgery->handle()
#20 /var/www/html/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#26 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#28 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#29 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#30 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#33 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#34 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#35 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}()
#36 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware/EnsureGetOnRedirect.php(19): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#37 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware\EnsureGetOnRedirect->handle()
#38 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#39 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#40 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#41 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#42 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#43 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#44 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#45 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#46 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#47 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#48 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#49 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#50 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#51 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#52 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#53 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#54 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#55 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#56 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#57 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#58 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#59 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle()
#60 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call()
#61 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json()
#62 /var/www/html/tests/Feature/ShopeeApiHardeningTest.php(692): Illuminate\Foundation\Testing\TestCase->postJson()
#63 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1318): Tests\Feature\ShopeeApiHardeningTest->test_sync_routes_reject_overlapping_sync_for_same_account()
#64 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod()
#65 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(361): PHPUnit\Framework\TestRunner->run()
#68 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 /var/www/html/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 /var/www/html/vendor/phpunit/phpunit/src/TextUI/Application.php(229): PHPUnit\TextUI\TestRunner->run()
#71 /var/www/html/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#72 {main}

----------------------------------------------------------------------------------

Class "App\Http\Controllers\Cache" not found

  at tests/Feature/ShopeeApiHardeningTest.php:693
    689▕                 'integrations.shopee-api.sync-escrow',
    690▕             ] as $route) {
    691▕                 $this->actingAs($user)
    692▕                     ->postJson(route($route))
  ➜ 693▕                     ->assertStatus(409)
    694▕                     ->assertJson([
    695▕                         'ok' => false,
    696▕                         'error' => 'A Shopee sync is already running for this account.',
    697▕                     ]);

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ShopeeApiHardeningTest > sync lock is scoped to account                                                                    
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Class "App\Http\Controllers\Cache" not found in /var/www/html/app/Http/Controllers/ShopeeApiController.php:330
Stack trace:
#0 /var/www/html/app/Http/Controllers/ShopeeApiController.php(263): App\Http\Controllers\ShopeeApiController->withSyncLock()
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\Http\Controllers\ShopeeApiController->syncOrders()
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\Routing\ControllerDispatcher->dispatch()
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(216): Illuminate\Routing\Route->runController()
#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(822): Illuminate\Routing\Route->run()
#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->{closure:Illuminate\Routing\Router::runRouteWithinStack():821}()
#6 /var/www/html/app/Http/Middleware/EnsureAccountIsActive.php(16): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\EnsureAccountIsActive->handle()
#8 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware.php(147): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware->handle()
#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(52): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(168): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(129): Illuminate\Routing\Middleware\ThrottleRequests->handleRequest()
#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php(90): Illuminate\Routing\Middleware\ThrottleRequests->handleRequestUsingNamedLimiter()
#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\ThrottleRequests->handle()
#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php(104): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestForgery->handle()
#20 /var/www/html/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#26 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#28 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#29 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#30 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#33 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#34 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#35 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->{closure:Illuminate\Foundation\Http\Kernel::dispatchToRouter():197}()
#36 /var/www/html/vendor/inertiajs/inertia-laravel/src/Middleware/EnsureGetOnRedirect.php(19): Illuminate\Pipeline\Pipeline->{closure:Illuminate\Pipeline\Pipeline::prepareDestination():178}()
#37 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Inertia\Middleware\EnsureGetOnRedirect->handle()
#38 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#39 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#40 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#41 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#42 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#43 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#44 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#45 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#46 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(110): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#47 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#48 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#49 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#50 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#51 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#52 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#53 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#54 /var/www/html/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(28): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#55 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#56 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->{closure:{closure:Illuminate\Pipeline\Pipeline::carry():194}:195}()
#57 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#58 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#59 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(638): Illuminate\Foundation\Http\Kernel->handle()
#60 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(604): Illuminate\Foundation\Testing\TestCase->call()
#61 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(411): Illuminate\Foundation\Testing\TestCase->json()
#62 /var/www/html/tests/Feature/ShopeeApiHardeningTest.php(726): Illuminate\Foundation\Testing\TestCase->postJson()
#63 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1318): Tests\Feature\ShopeeApiHardeningTest->test_sync_lock_is_scoped_to_account()
#64 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(1355): PHPUnit\Framework\TestCase->invokeTestMethod()
#65 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(521): PHPUnit\Framework\TestCase->runTest()
#66 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(99): PHPUnit\Framework\TestCase->runBare()
#67 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestCase.php(361): PHPUnit\Framework\TestRunner->run()
#68 /var/www/html/vendor/phpunit/phpunit/src/Framework/TestSuite.php(374): PHPUnit\Framework\TestCase->run()
#69 /var/www/html/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#70 /var/www/html/vendor/phpunit/phpunit/src/TextUI/Application.php(229): PHPUnit\TextUI\TestRunner->run()
#71 /var/www/html/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#72 {main}

----------------------------------------------------------------------------------

Class "App\Http\Controllers\Cache" not found

  at tests/Feature/ShopeeApiHardeningTest.php:727
    723▕ 
    724▕         try {
    725▕             $this->actingAs($userB)
    726▕                 ->postJson(route('integrations.shopee-api.sync-orders'))
  ➜ 727▕                 ->assertOk()
    728▕                 ->assertJson(['ok' => true]);
    729▕         } finally {
    730▕             $lock->release();
    731▕         }


  Tests:    6 failed, 12 passed (80 assertions)
  Duration: 9.19s

debian@DESKTOP-PEAOSJ9:~/projects/marketplace-analytics$ 