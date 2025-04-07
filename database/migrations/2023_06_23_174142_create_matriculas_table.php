<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatriculasTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('matriculas', function(Blueprint $table)
		{
			$table->integer('id');
			$table->integer('id_cliente')->default(0);
			$table->integer('id_curso')->default(0);
			$table->integer('id_responsavel');
			$table->string('id_turma', 11);
			$table->dateTime('data');
			$table->string('aluno', 300);
			$table->text('Descricao')->nullable();
			$table->integer('status')->nullable();
			$table->string('etapa_atual', 3);
			$table->float('valor', 11);
			$table->enum('situacao', array('n','a','p','g'))->comment('n = nada;a = andamento; p= perda; g=ganho');
			$table->string('motivo_situacao', 50);
			$table->string('responsavel', 300);
			$table->enum('agendamento', array('n','s'));
			$table->date('data_agendamento');
			$table->dateTime('data_matricula');
			$table->dateTime('data_contrato');
			$table->text('contrato');
			$table->dateTime('data_conclusao');
			$table->dateTime('data_certificado');
			$table->dateTime('data_certificado_atual');
			$table->dateTime('data_cancela_agenda');
			$table->dateTime('data_solicit_certificado');
			$table->dateTime('data_inicio');
			$table->dateTime('data_blacklist');
			$table->dateTime('data_documento');
			$table->time('hora_agendamento');
			$table->string('confirmar_agenda', 1);
			$table->integer('numero');
			$table->text('obs');
			$table->integer('validade');
			$table->integer('parcelamento');
			$table->float('valor_parcela', 12);
			$table->string('token', 50)->nullable();
			$table->integer('autor')->nullable();
			$table->dateTime('atualizado');
			$table->enum('cobranca_gerada', array('n','s'));
			$table->integer('seguido_por');
			$table->dateTime('data_seguir');
			$table->integer('setor');
			$table->text('tag');
			$table->integer('pontos');
			$table->text('tag_sys');
			$table->enum('ativo', array('s','n'))->nullable();
			$table->enum('notific', array('n','s'));
			$table->text('historico');
			$table->text('pagamento_asaas');
			$table->integer('telemark_operador');
			$table->integer('telemark_campanha');
			$table->date('telemark_inicio');
			$table->date('telemark_fim');
			$table->date('telemark_data_filtro');
			$table->enum('telemark_atendido', array('n','s'));
			$table->dateTime('telemark_data_sms');
			$table->text('telemark_notific_reg');
			$table->text('telemark_sms_reg');
			$table->text('telemark_token_lista');
			$table->string('token_atendimento', 80);
			$table->time('tempo_atendimento');
			$table->date('data_situacao');
			$table->string('token_externo', 128)->nullable()->comment('para integração com api');
			$table->enum('excluido', array('n','s'));
			$table->text('reg_excluido')->nullable();
			$table->enum('deletado', array('n','s'));
			$table->text('reg_deletado')->nullable();
			$table->float('desconto', 12);
			$table->float('subtotal', 12);
			$table->float('total', 16);
			$table->float('porcentagem_comissao', 12);
			$table->float('valor_comissao', 16);
			$table->enum('TipoDesconto', array('p','v'));
			$table->text('reg_inscricao');
			$table->text('reg_pagamento');
			$table->text('reg_agendamento');
			$table->text('memo');
			$table->text('orc');
			$table->text('fiador');
			$table->text('totais');
			$table->text('proposta');
			$table->integer('visualiza_pagina');
			$table->integer('visualiza_proposta');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('matriculas');
	}

}
