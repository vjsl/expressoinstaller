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
#
#
#####################################################################

echo " A Ideia é transformar este script em um adicionador de domínios ao ambiente expressoBr..."
echo " Ele não está iniciado..."
echo " Caso queira contribuir, altere este script para que adicione um novo domínio ao ambiente."

# Requisitos :
# - Não necessita instalar nenhum serviço, pois o script de instalação já o fez.
# - Tem que perguntar a linguagem, ou, dominio e senha
# - Tem que criar o diretório do novo dominio
# - Tem que criar o config para o novo dominio
# - Tem que criar o banco de dados para o novo dominio(dúvida? Como osar o banco antigo)
# - Ttem que criar a estrutura ldap para o novo dominio
# - Se usar um banco novo deve instalar aplicaçoes via setup

CUR_LANG="pt_br"

# Variaveis utilizadas pelo instalador
LDAP_IP=""
IP_IMAP_SERVER=""
SMTP_IP=""
APACHE_IP=""

THE_PASSWORD=""				# General Password

MY_ORG=""					#Organization
MY_DOMAIN=""				#Domain
MY_FQD=""					#Domain with hostname
MY_DC=""					#My LDAP DC

my_script=$( readlink -f "$0" )
my_path=$( dirname "$my_script" )

log="$my_path/install.log"

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

# Aqui seleciona a linguagem do sistema
its_ok=0
while [ $its_ok -eq 0 ]; do
	act_title=$( show_msg 5 )
	act_msg=$( echo -e "-Selecione um idioma:\n\n-Seleccionar un idioma:\n\n-Select a language:\n" )
	act_answer=$( dialog --backtitle "$act_backtitle" --stdout --title  "$act_title" --radiolist "$act_msg" 0 0 0 \
						Portugues-Brasil '' on \
						Español '' off \
						English '' off 
				)

	if [ $? -ne 0 ]; then
		echo -e "Instalador cancelado.\n\nInstallation aborted\n\nInstalación abortada.\n\n"
		exit 0
	fi

	case $act_answer in
		Portugues-Brasil)
			CUR_LANG="pt_br"
			its_ok=1 ;;			
		Español)
			CUR_LANG="esp" 		
			dialog --backtitle "$act_backtitle" --title "Idioma" --msgbox "Esta funcion no esta implementada todavia, ayudanos!" 6 40
			its_ok=0;;
		English)
			CUR_LANG="eng" 	
			its_ok=1;;
#			dialog --backtitle "$act_backtitle" --title "Language" --msgbox "This feature is not yet implemented, help us!" 6 40
#			its_ok=0;;
		*) 
			its_ok=0;;
	esac
done

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

#    Loop para dominios

	# Pegando o nome da organizacao
	get_organization

	# Pegando o nome do dominio
	get_domain

	# Gerando o DC para o dominio
	get_dc

        # Nome do banco e usuário
        get_db
	# Instalando os servicos

	# Apache2
	if [ $opt_apache -eq 1 ]; then
		. $my_path/libs/apache.lib
		config_apache
	fi
	# LDAP
	if [ $opt_ldap -eq 1 ]; then
		. $my_path/libs/ldap.lib
		install_ldap
	fi

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

		dialog --backtitle "$act_backtitle" --title "$act_title" --yesno "$act_msg" 0 0

		if [ $? -eq 0 ]; then
			# Caso seja solicitado configurar automaticamente, iniciara o processo por aqui...
			its_ok=0
			while [ $its_ok -eq 0 ]; do
				# Se todos os IPs dos servicos ja tiverem sido definidos, vai direto para a configuracao automatica, caso contrario, pede para definir o IP de cada servico...
				if [ "$LDAP_IP" ] && [ "$SMTP_IP" ] && [ "$IP_IMAP_SERVER" ]; then	
					its_ok=1

					act_msg=$( show_msg 81 )
					dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0

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
act_msg=$( show_msg 52 ) 

# Aviso de termino de instalacao
#dialog --backtitle "$act_backtitle" --backtitle "$act_title" --msgbox "$act_msg" 0 0

cd $my_path

show_msg 4

exit 1
