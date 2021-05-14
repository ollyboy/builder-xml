#get the builder XML files
php doxml.php console > doprice.log 2>&1
#get the latest runway plans
php runway_get_plan.php >> doprice.log 2>&1
#run the price compare, remove prod-post for dummy run
php builder.php HorizonDeerCreek-Prod David-SandBrock Highland-SandBrock Coventry prod-post >> doprice.log 2>&1
php builder.php Hillwood-demo demo-post >> doprice.log 2>&1
# php builder.php <developer> <builder-1> builder-2> demo-post >> doprice.log 2>&1
# check for errors
cat doprice.log | egrep 'ERROR|FATAL'
cat doprice.log | egrep 'WARN'

#end of sh
