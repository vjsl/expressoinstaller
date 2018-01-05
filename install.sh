#!/bin/bash

#####################################################################
#	Instalador do Expresso v3 / Expresso v3 Installer
#
#   Versão / Version: 0.95
#   Data / Date     : 28/05/2014
#
#   Criado por      : Dayvison Sathler / Created by: Dayvison Sathler
#   e-mail          : sathlerds@gmail.com
#   Contribuidores  : mguazzardo, julio, Daniel Coletti
#
#####################################################################

# COLOQUE ABAIXO O ENDEREÇO COMPLETO PARA DOWNLOAD DO EXPRESSO  E TAMBÉM O NOME EXATO DO PACOTE A SER BAIXADO
# URL for download of expresso v3 package

#URL_DOWNLOAD='http://10.31.80.38/pacotesKristina/kristina.20150223.08/tine20-allinone_kristina.20150223.08.tar.bz2'
#URL_DOWNLOAD='http://comunidadeexpresso.serpro.gov.br/portal/downloads/tine20-allinone_ExpressoBr.20150619.00.RC1.tar.bz2'
#URL_DOWNLOAD='http://comunidadeexpresso.serpro.gov.br/portal/downloads/ExpressoBr.20160307.00C.tar.bz2'
#URL_DOWNLOAD='http://comunidadeexpresso.serpro.gov.br/portal/downloads/ExpressoBr.20160307.00C.tar.bz2'
#URL_DOWNLOAD='http://comunidadeexpresso.serpro.gov.br/portal/downloads/ExpressoBr.20160307.00C.tar.bz2'
#URL_DOWNLOAD='http://pacotes.expdes.pae.serpro/ExpressoBr.20160719.00.RC2/tine20-allinone_ExpressoBr.20160719.00.RC2.tar.bz2'
URL_DOWNLOAD='http://comunidadeexpresso.serpro.gov.br/portal/downloads/tine20-allinone_ExpressoBr.20161221.01.tar.bz2'
#EXPRESSO_PACKAGE='ExpressoBr.20160307.00C.tar.bz2'
EXPRESSO_PACKAGE='tine20-allinone_ExpressoBr.20161221.01.tar.bz2'

# Current Languages:
# pt_br
# esp (not yet)
# eng (not yet)
CUR_LANG="pt_br"

# Programas necessarios para o funcionamento do instalador
CMDS="tar awk sed dialog dpkg grep debconf-apt-progress locale find wget stdbuf chkconfig"

# Variaveis utilizadas pelo instalador
LDAP_IP=""
IP_IMAP_SERVER=""
SMTP_IP=""
APACHE_IP=""

THE_PASSWORD=""				# General Password

MY_ORG=""				#Organization
MY_DOMAIN=""				#Domain
MY_FQD=""				#Domain with hostname
MY_DC=""				#My LDAP DC

# Configuracoes do Proxy
PROXY_IP=""
PROXY_USER=""
PROXY_PWD=""
PROXY_PORT=""

# indica em qual pasta sera criado o backup do expressov3
backup_folder="/var/www/html/backup-expressov3-$( date +%y%m%d-%H%M%S )"

#============================================================

PROXY_TYPE=0  # 0=sem proxy, 1=proxy com autenticacao, 2=proxy sem autenticacao

my_script=$( readlink -f "$0" )
my_path=$( dirname "$my_script" )

log="$my_path/install.log"

# Checa se existe o arquivo functions.lib, se nao existir, sai do instalador
if [ -f $my_path/libs/functions.lib ]; then
	. $my_path/libs/functions.lib
else
	echo "Arquivo functions.lib nao encontrado, saindo..."
	echo "File functions.lib not found, exiting..."
	exit 0
fi

act_backtitle=$( show_msg 5 )

# Checa se voce esta com privilegios de root para continuar
if [ "$( is_root )" == "Fail" ]; then
	show_msg 7
	show_msg 4
	echo " "
	exit 1
fi

# Apaga o arquivo de log para iniciar o instalador
# O log ainda nao foi implementado, mas este seria o inicio, fique a vontade para implemanta-lo
if [ -f $my_path/install.log ]; then
	echo > " "
fi

# Garante a permissao de execucao de todos os scripts
chmod +x $my_path/libs/*

# Variaveis que determinam o sistema operacional
my_os=$( so_version | awk -F ':' '{print $1}' )
my_os_version=$( so_version | awk -F ':' '{print $2}' )

# Suporte de instalação para os sistemas operacionais
# Por enquanto, apenas o debian 7!!!
case $my_os in
	"debian")
		if [ $my_os_version -ne 7 ]; then
			show_msg 10
			show_msg 4
			exit 0
		fi
		;;
	*)
		show_msg 11
		show_msg 4
		exit 0
		;;
esac

# Configura o locales no sistema
config_locale

# Verifica se o locales realmente foi configurado
if [ $( locale | grep -c "LANG=pt_BR.UTF-8" ) -ne 1 ]; then
	show_msg 16
	echo " "
	show_msg 4
	exit 0
fi

# Corrige um bug provocado no debconf antes de instalar os pacotes
if [ -f /usr/share/debconf/fix_db.pl ]; then
	 /usr/share/debconf/fix_db.pl &> /dev/null
fi

# Checando comandos ausentes necessarios, caso nao exista, vai instalar os pacotes
for the_prog in $CMDS; do
	if [ "$( find_prog $the_prog )" == "Fail" ]; then
		clear
		show_msg 2
		echo " "
		echo $( show_msg 3 )": $the_prog"
		echo " "
		case "$the_prog" in
			"awk") 						specific="mawk" 				;;
			"debconf-apt-progress") 	specific="debconf" 				;;
			"locale") 					specific="utils-linux-locales"	;;
			"find") 					specific="findutils"			;;
			"dns")	 					specific="dnsutils"				;;
			*)
				specific="$the_prog" ;;
		esac
		apt-get -y -qq install $specific
	fi
done

# Checando se realmente voce esta utilizando o SO suportado
case $my_os in
	debian)
		if [ $my_os_version -ne 7 ]; then
			act_title=$( show_msg 14 )
			act_msg=$( show_msg 10 )
			dialog --backtitle "$act_backtitle" --title "$act_title" --msgbox "$act_msg" 8 50
			exit 0
		fi
		;;
	*)
		act_title=$( show_msg 14 )
		act_msg=$( show_msg 10 )
		dialog --backtitle "$act_backtitle" --title "$act_title" --msgbox "$act_msg" 8 50
		exit 0
		;;
esac

# RCS: REMOVIDO PARA INSTALAR SEMPRE EM PT_BR
# Aqui seleciona a linguagem do sistema
#its_ok=0
#while [ $its_ok -eq 0 ]; do
#	act_title=$( show_msg 5 )
#	act_msg=$( echo -e "-Selecione um idioma:\n\n-Seleccionar un idioma:\n\n-Select a language:\n" )
#	act_answer=$( dialog --backtitle "$act_backtitle" --stdout --title  "$act_title" --radiolist "$act_msg" 0 0 0 \
#						Portugues-Brasil '' on \
#						Español '' off \
#						English '' off
#				)
#
#	if [ $? -ne 0 ]; then
#		echo -e "Instalador cancelado.\n\nInstallation aborted\n\nInstalación abortada.\n\n"
#		exit 0
#	fi
#
#	case $act_answer in
#		Portugues-Brasil)
#			CUR_LANG="pt_br"
#			its_ok=1 ;;
#		Español)
#			CUR_LANG="esp"
#			dialog --backtitle "$act_backtitle" --title "Idioma" --msgbox "Esta funcion no esta implementada todavia, ayudanos!" 6 40
#			its_ok=0;;
#		English)
#			CUR_LANG="eng"
#			its_ok=1;;
##			dialog --backtitle "$act_backtitle" --title "Language" --msgbox "This feature is not yet implemented, help us!" 6 40
##			its_ok=0;;
#		*)
#			its_ok=0;;
#	esac
#done

# RCS:
# define a lingua para portugues
CUR_LANG="pt_br"

clear

act_title=$( show_msg 5 )
act_button=$( show_msg 17 )

# Exibe a caixa de boas vindas de acordo com a linguagem selecionada
case "$CUR_LANG" in
	"pt_br")
		dialog --backtitle "$act_backtitle" --exit-label "$act_button" --title "$act_title" --textbox $my_path/libs/welcome-pt_br.txt 0 50
		;;
	"esp")
		dialog --backtitle "$act_backtitle" --exit-label "$act_button" --title "$act_title" --textbox $my_path/libs/welcome-esp.txt 0 50
		;;
	"eng")
		dialog --backtitle "$act_backtitle" --exit-label "$act_button" --title "$act_title" --textbox $my_path/libs/welcome-eng.txt 0 50
		;;
	*)
		show_msg 4
		exit 0
		;;
esac

# so vai exibir a opcao de UPDATE se encontrar a pasta /var/www/html/expressov3 na maquina
if [ -d /var/www/html/expressov3 ]; then
	# Aqui o usuario seleciona se quer instalar o expresso do zero ou fazer um update para nova versao
	its_ok=0
	while [ $its_ok -eq 0 ]; do
		act_title=$( show_msg 87 )
		act_msg=$( show_msg 88 )
		act_option1=$( show_msg 91 )
		act_option2=$( show_msg 92 )
		opt1_text=$( show_msg 89 )
		opt2_text=$( show_msg 90 )
		act_answer=$( dialog --backtitle "$act_backtitle" --stdout --title  "$act_title" --radiolist "$act_msg" 0 0 0 \
							$act_option2 "$opt2_text" on \
							$act_option1 "$opt1_text" off \
					)

		if [ $? -ne 0 ]; then
			show_msg 12
			show_msg 4
			exit 0
		fi

		if [ ! $act_answer ]; then
			act_msg=$( show_msg 94 )
			dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0
		fi

		SETUP_TYPE=""
		# Opcoes de configuracao do Expresso
		#SETUP_TYPE="install" --> instala o expresso
		#SETUP_TYPE="update"  --> atualiza o expresso

		case $act_answer in
			$act_option1)
				SETUP_TYPE="install"
				its_ok=1
				;;
			$act_option2)
				# verifica se o web server realmente existe antes de fazer update
				if [ "$( find_prog apache2 )" == "Fail" ]; then
					#se o apache nao estiver instalado, entao nao vai poder continuar com esta opcao. Vai exibir um aviso
					act_title=$( show_msg 94 )
					act_msg=$( show_msg 93 )
					dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0
					its_ok=0
				else
					SETUP_TYPE="update"
					its_ok=1
				fi
				;;
			*)
				its_ok=0
				;;
		esac
	done
else
	SETUP_TYPE="install"
fi

# Cria a pasta de backup caso seja necessario fazer backup de algo antes
if [ ! -d $backup_folder ]; then
	mkdir -p $backup_folder
fi

# SE FOR APENAS UPDATE
if [ "$SETUP_TYPE" == "update" ]; then
	. $my_path/libs/apache.lib
	config_apache
else
# SE FOR INSTALACAO DO ZERO

	## RCS: REMOVIDO PARA NAO EXIBIR A TELA DE UPDATE/UPGRADE
	## HE: Permitido para o Expresso da comunidade
	## Atualizando o apt-get antes de continuar
	act_title=$( show_msg 5 )
	act_msg=$( show_msg 8 )
	dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "$act_msg" 0 0

	if [ $? -eq 0 ]; then
			debconf-apt-progress -- apt-get update &> /dev/stdout
	fi

	## Faz o upgrade do apt-get antes de continuar
	act_title=$( show_msg 5 )
	act_msg=$( show_msg 9 )
	dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "$act_msg" 0 0

	if [ $? -eq 0 ]; then
		debconf-apt-progress -- apt-get -qq -y upgrade &> /dev/stdout
	fi

	# Instalando alguns pacotes basicos
	debconf-apt-progress -- apt-get -qq -y install openssh-server openssh-client nmap lvm2 rsync telnet bzip2 pv dnsutils


	## RCS: REMOVIDO PARA FORCAR UMA ATUALIZACAO COMPLETA
	## Selecionando o tipo de instalacao desejada (Tudo ou personalizada)
	#act_title=$( show_msg 58 )
	#act_msg=$( show_msg 59 )
	#desc1=$( show_msg 60 )
	#desc2=$( show_msg 61 )
	#act_answer=$( dialog --backtitle "$act_backtitle" --stdout --title  "$act_title" --radiolist "$act_msg" 0 0 0 \
	#						"1" "$desc1" on \
	#						"2" "$desc2" off \
	#				)
	#
	#if [ $? -ne 0 ]; then
	#	show_msg 12
	#	show_msg 4
	#	exit 0
	#fi

	act_answer=1

	case $act_answer in
			1)
				# Se for selecionado todos os serviços
				all_services=1
				opt_apache=1
				opt_ldap=1
				opt_db=1
				opt_imap=1
				opt_smtp=1
				opt_sasl=1
				;;
			2)
				# Se for instalacao personalizada, vai exibir um dialogo para selecao dos servicos

				act_msg=$( show_msg 18 )
				option1=$( show_msg 19 ) #Apache
				option2=$( show_msg 20 ) #LDAP
				option3=$( show_msg 21 ) #Postgresql
				option4=$( show_msg 22 ) #Cyrus
				option5=$( show_msg 23 ) #Postfix

				selected_services=$( dialog --backtitle "$act_backtitle" --stdout --checklist "$act_msg" 0 0 0 \
									Apache2-PHP5 "$option1" ON  \
									OpenLDAP "$option2" ON  \
									PostgreSQL "$option3" ON \
									Cyrus-IMAP "$option4" ON \
									Postfix "$option5" ON
				)

				if [ $? -ne 0 ] || [ ! "$selected_services" ]; then
					show_msg 12
					show_msg 4
					exit 0
				else
					# Checa se mesmo assim todos os servicos foram selecionados, ajustando a variavel all_services
					if [ $( echo $selected_services | wc -w  ) -eq 5 ]; then
						all_services=1
					else
						all_services=0
					fi

					opt_apache=0
					opt_ldap=0
					opt_db=0
					opt_imap=0
					opt_smtp=0
					opt_sasl=0

					# Seta as variaveis correspondentes aos servicos selecionados
					for each_service in $selected_services; do
						case $each_service in
							'"Apache2-PHP5"') opt_apache=1 ;;
							'"OpenLDAP"') opt_ldap=1 ;;
							'"PostgreSQL"') opt_db=1 ;;
							'"Cyrus-IMAP"') opt_imap=1 ;;
							'"Postfix"') opt_smtp=1	;;
						esac
					done
				fi
				;;
			*)
				show_msg 12
				show_msg 4
				exit 0
				;;
	esac


	# Loop para dominios

	if [ $opt_imap -eq 1 ] || [ $opt_smtp -eq 1 ] || [ $all_services -eq 1]; then
		opt_sasl=1
		. $my_path/libs/sasl.lib
	fi

	# Pegando o nome da organizacao
	get_organization

	# Pegando o nome do dominio
	get_domain

	# Gerando o DC para o dominio
	get_dc

	#THE_PASSWORD="admin"

	# Obtendo a senha para a instalacao dos servicos de administracao
	if [ ! "$THE_PASSWORD" ]; then
        	get_password
        fi

	# RCS: removido para automatizar a instalacao
	# Checando por registro DNS do tipo MX para o correto funcionamento do correio
	#if [ $opt_smtp -eq 1 ]; then
	#	# checking mx for domain
	#	if [ "$( mx_verify )" == "Fail" ]; then
	#		act_title=$( show_msg 83 )
	#		act_msg=$( show_msg 82 )
	#		dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "$act_msg"  0 0
	#		if [ $? -ne 0 ]; then
	#			show_msg 12
	#			show_msg 4
	#			exit 0
	#		fi
	#	fi
	#fi

	# Instalando os servicos

	# Apache2
	if [ $opt_apache -eq 1 ]; then
		. $my_path/libs/apache.lib
		config_apache
	fi

        #dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "Passou pelo apache"  0 0

	# LDAP
	if [ $opt_ldap -eq 1 ]; then
		. $my_path/libs/ldap.lib
		install_ldap
	fi
        #dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "Passou pelo ldap"  0 0


	# PostgreSQL
	if [ $opt_db -eq 1 ]; then
		. $my_path/libs/db.lib
		install_db
	fi

	# Cyrus IMAP / Sieve
	if [ $opt_imap -eq 1 ]; then
		# Instala o SASL caso setado para ser instalado
		if [ $opt_sasl -eq 1 ]; then
			if [ "$( test_sasl )" == "Fail" ]; then
				install_sasl
			fi
			while [ "$( test_sasl )" == "Fail" ]; do
				act_nolabel=$( show_msg 39 )
				act_yeslabel=$( show_msg 40 )
				act_msg=$( show_msg 38 )
				act_title=$( show_msg 27 )

				dialog --backtitle "$act_backtitle" --no-label "$act_nolabel" --yes-label "$act_yeslabel"  --title "saslauth" --yesno "$act_msg"  0 0
				if [ $? -eq 0 ]; then
					break
				else
					install_sasl
				fi
			done
		else
			show_msg 4
			exit 0
		fi

		. $my_path/libs/imap.lib
		install_imap
	fi

	# SMTP Postfix
	if [ $opt_smtp -eq 1 ]; then
		# Instala o SASL caso setado para ser instalado
		if [ $opt_sasl -eq 1 ]; then
			if [ "$( test_sasl )" == "Fail" ]; then
				install_sasl
			fi

			while [ "$( test_sasl )" == "Fail" ]; do
				act_nolabel=$( show_msg 39 )
				act_yeslabel=$( show_msg 40 )
				act_msg=$( show_msg 38 )
				act_title=$( show_msg 27 )

				dialog --backtitle "$act_backtitle" --no-label "$act_nolabel" --yes-label "$act_yeslabel"  --title "saslauth" --yesno "$act_msg"  0 0

				if [ $? -eq 0 ]; then
					break
				else
					install_sasl
				fi
			done
		else
			show_msg 4
			exit 0
		fi

		. $my_path/libs/smtp.lib
		install_smtp

	fi

	# Caso o servico WEB esteja nesta maquina, vai perguntar se deseja configurar o setup automaticamente via linha de comando.
	if [ $opt_apache -eq 1 ]; then

		act_title=$( show_msg 76 )
		act_msg=$( show_msg 77 )

		# RCS: FORCA UMA INSTALACAO AUTOMATICA DO EXPRESSO
		#act_answer=$( dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "$act_msg" 0 0 )

		act_answer=0

		if [ $act_answer -eq 0 ]; then
			# Caso seja solicitado configurar automaticamente, iniciara o processo por aqui...
			its_ok=0
			while [ $its_ok -eq 0 ]; do
				# Se todos os IPs dos servicos ja tiverem sido definidos, vai direto para a configuracao automatica, caso contrario, pede para definir o IP de cada servico...
				if [ "$LDAP_IP" ] && [ "$SMTP_IP" ] && [ "$IP_IMAP_SERVER" ]; then
					its_ok=1

					#act_msg=$( show_msg 81 )
					#dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0

					rm -r /tmp/zend_cache* &> /dev/null

					# Chama o setup.lib que faz a configuracao automatica via linha de comando
					. $my_path/libs/setup.lib
					configure_setup

					break
				else
					if [ $opt_ldap -eq 1 ]; then
						LDAP_IP="127.0.0.1"
					else
						get_ldap_ip
					fi

					if [ $opt_smtp -eq 1 ]; then
						SMTP_IP="127.0.0.1"
					else
						get_smtp_ip
					fi

					if [ $opt_imap -eq 1 ]; then
						IP_IMAP_SERVER="127.0.0.1"
					else
						get_imap_ip
					fi
					its_ok=0
				fi
			done
		else
			# Caso nao queira configurar o setup automaticamente, exibe uma mensagem de instrucao para entrar no browser e configurar la...
			act_title=$( show_msg 52 )
			APACHE_IP=$( get_host_ip )
			act_msg=$( echo "$( show_msg 78 ) http://"$APACHE_IP"/setup.php" )
			dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0
		fi
	fi

# fim da condicao de instalacao do expresso do zero
fi

# Volta com a interatividade do apt-get para instalacoes do usuario
unset DEBIAN_FRONTEND

act_title=$( show_msg 5 )
act_msg=" FINAL DA INSTALAÇÃO ExpressoBR\\n\nOrganization: $MY_ORG\\nBanco de dados: db_$MY_ORG\\nUsuário Banco: user_$MY_ORG\\nSenha Banco: $THE_PASSWORD"
act_msg=$act_msg"\\nDominio : $MY_DOMAIN\\nDC : $MY_DC"
act_msg=$act_msg"\\nAdministrador Expresso: expresso-admin@$MY_DOMAIN\\nSenha : $THE_PASSWORD"
act_msg=$act_msg"\\nUsuário Setup: tine-admin\\nSenha : $THE_PASSWORD"
act_msg=$act_msg"\\nLogs e Caches do Expresso: /tmp/expressov3/$MY_DOMAIN"
act_msg=$act_msg"\\nObs: Verifique a ativação dos caches e o nivel de logs para ambientes em produção"

# Aviso de termino de instalacao
dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0

cd $my_path

show_msg 4
echo $act_msg >> $log
exit 1
