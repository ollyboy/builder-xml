#get the builder XML files
php doxml.php console > doprice.log 2>&1
#get the latest runway plans
php runway_get_plan.php >> doprice.log 2>&1
#run the price compare
php builder.php HorizonDeerCreek-Prod David-SandBrock Highland-SandBrock >> doprice.log 2>&1
# check for errors
cat doprice.log | grep ERROR

#end of sh

