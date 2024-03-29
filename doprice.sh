#get the builder XML files, get runway plans, update price

# go to the main folder
cd /home/ec2-user/builder-xml

# log the start of a run
echo "---- Do price run" >> /tmp/dp-run.log
date >> /tmp/dp-run.log

# get the XML file, wake Perry up first
/usr/bin/php doxml.php console Perry > doprice.log 2>&1
sleep 2m
/usr/bin/php doxml.php console > doprice.log 2>&1

#get the latest runway plans
/usr/bin/php runway_get_plan.php HorizonDeerCreek-Prod Hillwood-Prod HowardHughes-Prod >> doprice.log 2>&1

#run the price compare, remove prod-post for dummy run
/usr/bin/php builder.php HorizonDeerCreek-Prod prod-post >> doprice.log 2>&1
#/usr/bin/php builder.php HorizonDeerCreek-Demo David-SandBrock Coventry demo-post lot-update >> doprice.log 2>&1
#/usr/bin/php builder.php Hillwood-Demo demo-post lot-update >> doprice.log 2>&1
/usr/bin/php builder.php Hillwood-Prod prod-post lot-update >> doprice.log 2>&1
#/usr/bin/php builder.php HowardHughes-Demo demo-post lot-update >> doprice.log 2>&1
/usr/bin/php builder.php HowardHughes-Prod prod-post lot-update >> doprice.log 2>&1
# example use - php builder.php <developer> <builder-1> builder-2> demo-post >> doprice.log 2>&1

# check for errors
cat doprice.log | egrep 'ERROR|FATAL' >> /tmp/dp-run.log
cat doprice.log | egrep 'ERROR|FATAL' >  error.match.log

# send files to Gdrive
rclone copy . --include "*.match.*" runway-drop-files: >> doprice.log 2>&1

# log ending times
date >> /tmp/dp-run.log
echo "----- Do price end" >> /tmp/dp-run.log
#
echo "----- Do price Warn" >> /tmp/dp-warn.log
date >> /tmp/dp-warn.log
cat doprice.log | egrep 'WARN' >> /tmp/dp-warn.log

#end of sh
