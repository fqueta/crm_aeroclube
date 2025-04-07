import os
link_git = "https://github.com/fqueta/api.git"
path = "/home/aeroclu/public_html"
usuario = "aeroclu"
dir = "api"
nome_dir = path+"/"+dir
nome_link = "api_lar"
dow = "cd "+path+" && rm -rf "+dir+" && git clone "+link_git
composer = "cd "+nome_dir+" && composer install -o --no-dev && cp .env.example .env"
permissao = "chmod 755 "+dir+" && find * -type d -exec chmod 755 {} \; && find * -type f -exec chmod 644 {} \;"
cache = "cd "+nome_dir+" && php artisan key:generate && php artisan config:cache && php artisan route:cache && php artisan view:clear"
criar_link = "cd "+path+" && rm -rf "+nome_link+" && ln -s "+nome_dir+"/public "+nome_link
#cmd = "cd cmd && git pull && php artisan config:cache && php artisan route:cache && cd ../ita && git pull && php artisan config:cache && php artisan route:cache"
os.system(dow)
os.system(composer)
os.system(permissao)
os.system(cache)
os.system(criar_link)

