<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('clientes', function(Blueprint $table)
		{
			$table->integer('id');
			$table->string('token', 50);
			$table->enum('ativo', array('s','n'));
			$table->string('Respons', 50)->nullable();
			$table->integer('CodStatus')->nullable();
			$table->timestamp('data')->default(\DB::raw('CURRENT_TIMESTAMP'));
			$table->dateTime('DtMatricula')->nullable();
			// $table->dateTime('DtEmissaoContrato')->default('0000-00-00 00:00:00');
			$table->date('DtInitCurso')->nullable();
			$table->date('DtFimCurso')->nullable();
			$table->dateTime('atualizado');
			$table->date('DtNasc')->nullable();
			$table->string('Cpf', 25)->nullable();
			$table->string('sexo', 2);
			$table->string('Ident', 20)->nullable();
			$table->string('Endereco', 50)->nullable();
			$table->integer('Numero')->nullable();
			$table->string('Compl', 20)->nullable();
			$table->string('Bairro', 30)->nullable();
			$table->string('Cidade', 30)->nullable();
			$table->string('Uf', 2)->nullable();
			$table->string('Cep', 10)->nullable();
			$table->string('Tel', 15)->nullable();
			$table->string('Contato', 30)->nullable();
			$table->string('Celular', 15)->nullable();
			$table->string('Email', 150)->nullable();
			$table->string('Nome', 300)->nullable();
			$table->string('sobrenome', 300);
			$table->date('DtNasc2')->nullable();
			$table->string('telefonezap', 30)->nullable();
			$table->string('Contato2', 30)->nullable();
			$table->text('tag')->nullable();
			$table->string('genero', 50)->nullable();
			$table->string('pais', 100)->nullable();
			$table->string('nacionalidade', 100)->nullable();
			$table->string('orgao_emissor', 100)->nullable();
			$table->string('canac', 10)->nullable();
			$table->integer('autor')->nullable();
			$table->boolean('Indicar')->nullable();
			$table->string('Indicado', 40)->nullable();
			$table->text('rdstation')->nullable();
			$table->text('zapguru')->nullable();
			$table->integer('seguido_por')->nullable();
			$table->integer('CodOperador')->nullable();
			$table->date('DtAgenda')->nullable();
			$table->string('Obs', 75)->nullable();
			$table->date('EmissContrato')->nullable();
			$table->time('EmissContratoH')->nullable();
			$table->text('HorariosCurso')->nullable();
			$table->float('ValorCurso', 10, 0)->nullable();
			$table->float('Matricula', 10, 0)->nullable();
			$table->text('grupo');
			$table->string('Escolaridade', 14)->nullable();
			$table->smallInteger('NumCarta')->nullable();
			$table->integer('Numdecursos')->nullable();
			$table->smallInteger('NumCobranca')->nullable();
			$table->integer('NumContatos')->nullable();
			$table->string('profissao', 100)->nullable();
			$table->string('Pai', 40)->nullable();
			$table->string('Mae', 40)->nullable();
			$table->string('Naturalidade', 30)->nullable();
			$table->integer('BairroP')->nullable();
			$table->date('UltimoContato')->nullable();
			$table->string('OperadorTel', 20)->nullable();
			$table->date('DataRetorno')->nullable();
			$table->integer('Indicacao')->nullable();
			$table->string('cpfaluno', 25)->nullable();
			$table->char('SPC', 1)->default('N');
			$table->date('DtUltimaCobranca')->nullable();
			$table->string('estado_civil', 100);
			$table->string('UltStatusTele', 30)->default('0');
			$table->string('senha', 100)->nullable();
			$table->time('HoraUltStatusTele')->default('00:00:00');
			$table->string('id_asaas', 100);
			$table->date('Dt_Email')->nullable();
			$table->string('HoraRetorno', 10)->nullable();
			$table->date('emissaoCertif')->nullable();
			$table->timestamp('DtInclusao')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->text('memoria')->nullable();
			$table->text('config')->nullable();
			$table->string('local_cad', 50)->nullable();
			$table->string('Operador_Incluiu', 30)->nullable();
			$table->string('OperadorEmissaoContrato', 30)->nullable();
			// $table->dateTime('DtSondagem')->default('0000-00-00 00:00:00');
			// $table->dateTime('DtNegativado')->default('0000-00-00 00:00:00');
			$table->string('Operador_Matriculou', 30)->nullable();
			$table->string('OperadorEmissaoCertif', 30)->nullable();
			$table->date('DataiInformatica')->nullable();
			$table->date('DatafInformatica')->nullable();
			$table->string('HoraInformatica', 50)->nullable();
			$table->string('DiasInformatica', 50)->nullable();
			$table->integer('permissao')->nullable();
			$table->enum('verificado', array('n','s'));
			$table->string('usuario', 100)->nullable();
			$table->string('sessao', 90);
			$table->enum('logado', array('n','s'));
			$table->integer('huggy_id')->nullable()->default(0);
			$table->string('token_externo', 60)->nullable()->comment('para integração com api');
			$table->string('EscolhaDoc', 3);
			$table->enum('excluido', array('n','s'));
			$table->text('reg_excluido');
			$table->enum('deletado', array('n','s'));
			$table->text('reg_deletado');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('clientes');
	}

}
