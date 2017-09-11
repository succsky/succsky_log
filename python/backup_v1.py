import os
import time
source = ['/Users/gtc/Desktop/tmp/file','/Users/gtc/Desktop/tmp/try.php']
target_dir = '/Users/gtc/Desktop/tmp'
target = target_dir + time.strftime('%Y%m%d%H%M%S') + '.zip'
zip_command   =  'hello'
if os.system(zip_command) == 0:
	print 'successful',target
else:	
	print 'failed'
