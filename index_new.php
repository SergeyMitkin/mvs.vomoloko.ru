<?php

include 'db.conf.php';
include 'operations.php';

require_once('settings.php');

$dates = date('Ymd');
if(isset($_POST['date'])){
    list($d, $m, $y) = explode('.', $_POST['date']);
    $dates = $y.$m.$d;
}

$db = new MysqlWrapper();
$ip = $_SERVER['REMOTE_ADDR'];
$ip='188.187.108.49';
$sql = "SELECT id, name, address from retailpoints where ipv4 = '".$ip."';";


$data = $db->query($sql);

if (null != $data && null!=($row = $data->fetch_assoc())){
    $shopname = $row['name'];
    $shop_id = $row['id'];
}

$cashiers = array();
$sql = "SELECT id, name from marketers where retired is NULL order by name;";
$data = $db->query($sql);
if (null != $data ){
    while(null!=($row = $data->fetch_assoc())) {
        $cashiers[] = $row['name'];
    }
}

if(isset($shopname)){

    $date_dt=date_create($dates);
    $date_week_ago = date_add(clone $date_dt,date_interval_create_from_date_string("7 days ago"));

    $newAvgCheck= round(get_avg_check($shopname,$date_dt,$db));
    $oldAvgCheck= round(get_avg_check($shopname,$date_week_ago,$db));
    $bonus = $newAvgCheck > $oldAvgCheck ? $newAvgCheck : 0;
}
$db->disconnect();

$datadir = rtrim($datadir, '/').'/';
if (!is_dir($datadir.'operations')) mkdir($datadir.'operations');
if (!is_dir($datadir.'whoworked')) mkdir($datadir.'whoworked');

$previousEndAmount = 'не известно';
$startAmount = '-';
$endAmount = '-';

$last_operations = array();
if ($shopname !== false) {
	$filename = $datadir.'operations/'.$shopname.'_'.date('Y').'.'.date('m').'.csv';
	if (file_exists($filename)) {
		$csv = array();
		$rows = file($filename);
		array_shift($rows);
		foreach ($rows as $row) {
			$data = str_getcsv(trim(iconv('windows-1251', 'utf-8', $row)), ';');
			if ($data[0] == date('Ymd')) {
				$csv[] = $data;
				if ($data[2] == $actions['start']) {
					$startAmount = $data[3];
				}
				if ($data[2] == $actions['end']) {
					$endAmount = $data[3];
				}
			} elseif ($data[2] == $actions['end']) {
				$previousEndAmount = $data[3];
			}
			if (!action_visible($data[2])) $data[3] = '';
		}
		$last_operations = array_reverse(array_slice($csv, -5));
	} else {
		file_put_contents($filename, iconv('utf-8', 'windows-1251', '"Дата";"Время";"Действие";"Сумма";"Продавец";"Комментарий"')."\r\n");
	}
}

$last_whoworked = array();
if ($shopname !== false) {
	$filename = $datadir.'whoworked/'.$shopname.'_'.date('Y').'.'.date('m').'.csv';
	if (file_exists($filename)) {
		$csv = array();
		$rows = file($filename);
		array_shift($rows);
		foreach ($rows as $row) {
			$data = str_getcsv(trim(iconv('windows-1251', 'utf-8', $row)), ';');
			if ($data[0] == date('Ymd')) {
				$csv[] = $data;
			}
		}
		$last_whoworked = array_reverse(array_slice($csv, -5));
	} else {
		file_put_contents($filename, iconv('utf-8', 'windows-1251', '"Дата";"Время";"Продавец";"Сколько";"Комментарий"')."\r\n");
	}
}

$state_operation = '';
$js_pre_operation = 'false';
if (isset($_GET['o']) && in_array($_GET['o'], array_keys($operations))) {
	$state_operation = ' disabled';
	$js_pre_operation = 'true';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<title>Во!Молоко</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="css/jquery-ui.min.css" rel="stylesheet">
	<link href="css/bootstrap.min.css" rel="stylesheet">
	<link href="css/bootstrap-datetimepicker.css" rel="stylesheet">
	<link href="css/autocomplete.css" rel="stylesheet">
	<link href="css/style.css" rel="stylesheet">
	<script type="text/javascript" src="js/jquery-1.11.3.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui.min.js"></script>
	<script type="text/javascript" src="js/jquery.autocomplete.js"></script>
	<script type="text/javascript" src="js/moment.js"></script>
	<script type="text/javascript" src="js/locale/ru.js"></script>
	<script type="text/javascript" src="js/bootstrap.min.js"></script>
	<script type="text/javascript" src="js/bootstrap-datetimepicker.js"></script>
</head>
<body>
	<div class="logo"><img src="images/logo.png"></div>
	<div class="container-fluid main-wrapper">
		<div id="tabs">
			<div class="row">
				<div class="col-sm-4" style="min-width: 300px;">
					<div class="tabs-wrapper">
						<ul>
							<li><a href="#tab-1">Касса</a></li>
							<li><a href="#tab-2">Перемещения</a></li>
							<li><a href="getresults.php">Отчеты</a></li>
						</ul>
					</div>
				</div>
				<div class="col-sm-2 hide-on-tab3" style="padding-top: 0.2em;min-width: 170px;">
					<div class="form-group">
						<div class='input-group date' id='date'>
							<input type='text' name="date" class="form-control" />
							<span class="input-group-addon">
								<span class="glyphicon glyphicon-calendar"></span>
							</span>
		                </div>
					</div>
				</div>
				<div class="col-sm-6 hide-on-tab3" style="padding-top: 0.2em;">
					<h3 id="shopname"><?php echo $shopname; ?></h3>
				</div>
				<div class="col-sm-8 show-on-tab3" style="padding-top: 0.2em;display:none;">
					<select class="form-control" id="shop">
						<option value="">Выберите магазин</option>
						<?php
							foreach ($shops as $shop) {
								echo '<option value="'.$shop['title'].'">'.$shop['title'].'</option>';
							}
						?>
					</select>
				</div>
			</div>
		
			<div id="tab-1">
				<?php
					if ($shopname === false) {
						echo '<div class="well"><h3>Магазин не определен.</h3><p>Ваш ip-адрес: <strong>'.$ip.'</strong></p></div>';
					} else {
				?>
				<div class="row">
					
					<div class="col-sm-5">
						<form action="" method="post" class="form-horizontal" id="form-operations">
						<div class="form-group">
							<label for="action" class="col-sm-4 control-label">Действие</label>
							<div class="col-sm-8">
								<select class="form-control" id="action">
									<option value="">Выберите</option>
									<?php
										foreach ($actions as $key => $title) {
											echo '<option value="'.$key.'">'.$title.'</option>';
										}
									?>
								</select>
							</div>
						</div>
						
						<div class="form-group">
							<label for="cashier" class="col-sm-4 control-label">Продавец</label>
							<div class="col-sm-8">
								<select class="form-control" id="cashier">
								<option value="">Выберите</option>
								<?php
									foreach ($cashiers as $cashier) {
										echo '<option value="'.$cashier.'">'.$cashier.'</option>';
									}
								?>
								</select>
							</div>
						</div>
						
						<div class="form-group" id="group-amount">
							<label for="amount" class="col-sm-4 control-label">Сумма</label>
							<div class="col-sm-8">
								<input type="text" class="form-control" id="amount">
							</div>
						</div>
						<div class="form-group" id="group-howmuch" style="display:none;">
							<label for="howmuch" class="col-sm-4 control-label">Сколько</label>
							<div class="col-sm-8">
								<select class="form-control" id="howmuch">
									<option value="">Выберите</option>
									<option value="1">1</option>
									<option value="0.5">0.5</option>
								</select>
							</div>
						</div>
						
						<div class="form-group">
							<label for="comment2" class="col-sm-4 control-label">Комментарий</label>
							<div class="col-sm-8">
								<textarea class="form-control" id="comment2"></textarea>
							</div>
						</div>
						
						<div class="form-group">
							<div class="col-sm-offset-4 col-sm-8">
								<button type="submit" class="btn btn-primary" id="tab1-submit">Внести</button>
							</div>
						</div>
						</form>
						<br />
						<h4>Кто работал в смену</h4>
						<table class="table table-bordered table-condensed" id="table-whoworked">
							<thead>
								<tr>
									<th>Продавец</th>
									<th>Сколько</th>
									<th>Комментарий</th>
								</tr>
							</thead>
							<tbody>
							<?php
								foreach ($last_whoworked as $whoworked) {
									echo '<tr><td>'.$whoworked[2].'</td><td>'.$whoworked[3].'</td><td>'.$whoworked[4].'</td></tr>';
								}
							?>
							</tbody>
						</table>
					</div>
					
					<div class="col-sm-7">
						<h4>Закрытие прошлой смены: <strong><span id="previous-end-amount"><?php echo $previousEndAmount; ?></span></strong></h4>
						<h4>Сумма в начале смены: <strong><span id="start-amount"><?php echo $startAmount; ?></span></strong></h4><br/>
                        <h4>Средний чек на прошлой неделе: <strong><span id="old-avg-check"><?php echo $oldAvgCheck; ?></span></strong></h4>
                        <h4>Средний чек сегодня: <strong><span id="new-avg-check"><?php echo $newAvgCheck."(".(($oldAvgCheck>$newAvgCheck)?("".($newAvgCheck-$oldAvgCheck)):("+".($newAvgCheck-$oldAvgCheck))).")"; ?></span></strong></h4>
                        <h3>Бонус сегодня: <strong><span id="bonus"><?php echo $bonus; ?></span></strong></h3>
						
						<br />
						<h4>Операции за смену</h4>
						<table class="table table-bordered table-condensed" id="table-operations">
							<thead>
								<tr>
									<th>Действие</th>
									<th>Сумма</th>
									<th>Продавец</th>
									<th>Комментарий</th>
								</tr>
							</thead>
							<tbody>
							<?php
								foreach ($last_operations as $operation) {
									echo '<tr><td>'.$operation[2].'</td><td>'.$operation[3].'</td><td>'.$operation[4].'</td><td>'.$operation[5].'</td></tr>';
								}
							?>
							</tbody>
						</table>
						
						<h4>Сумма в конце смены: <strong><span id="end-amount"><?php echo $endAmount; ?></span></strong></h4>
					</div>
					
				</div>
				<?php } ?>
			</div>
			
			<div id="tab-2">
				<?php
					if ($shopname === false) {
						echo '<div class="well"><h3>Магазин не определен.</h3><p>Ваш ip-адрес: <strong>'.$ip.'</strong></p></div>';
					} else {
				?>
				<form action="" method="post">
				<div class="well">
					<div class="row">
						<div class="col-sm-12">
							<div class="form-group">
								<label for="operation">Операция</label>
								<select class="form-control" name="operation" id="operation"<?php echo $state_operation; ?>>
									<option value="">Выберите</option>
									<?php
										foreach ($operations as $id => $title) {
											echo '<option value="'.$id.'"'.(isset($_GET['o']) && $_GET['o'] == $id ? ' selected' : '').'>'.$title.'</option>';
										}
									?>
								</select>
							</div>
						</div>
					</div>
				</div>
				
				<div class="well">
					<div class="row">
						<div class="col-sm-9">
							<div class="form-group">
								<label for="item">Товар</label>
								<input type="text" name="item" class="form-control" id="item">
							</div>
						</div>

						<div class="col-sm-3">
							<div class="form-group">
								<label for="quantity">Количество</label>
								<input type="text" name="quantity" class="form-control" id="quantity">
							</div>
						</div>
					</div>
				</div>
				
				<div class="row">
					<div class="col-sm-12">
						<table class="table table-bordered table-condensed" id="table">
							<thead>
								<tr>
									<th>Код товара</th>
									<th>Штрих-код товара</th>
									<th>Название товара</th>
									<th>Количество</th>
									<th></th>
									<th></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
				
				<div class="well">
					<div class="row">
						<div class="col-sm-12">
							<div class="form-group">
								<label for="comment">Комментарий</label>
								<textarea name="comment" class="form-control" id="comment"></textarea>
							</div>
						</div>
					</div>
					
					<div class="row">
						<div class="col-sm-12">
							<div class="text-right">
								<div class="loading"></div>
								<button type="submit" class="btn btn-primary" id="submit">Отправить</button>
							</div>
						</div>
					</div>
				</div>
				</form>
				<?php } ?>
			</div>

		</div>
	</div>
	
	<script type="text/javascript">
		var selected = null;
		var editMode = false;
		var pre_operation = <?php echo $js_pre_operation; ?>;
		jQuery(document).ready(function($) {
			$('#tabs').tabs({
				active: 1,
				beforeActivate: function (event, ui) {
					if (ui.newPanel.attr('id') == 'tab-1') {
						reload_operations($('#date').data("date"));
					}
					if (ui.newPanel.attr('id') == 'ui-id-4') {
						$('.hide-on-tab3').hide();
						$('.show-on-tab3').show();
					} else {
						$('#shop').val('');
						$('.hide-on-tab3').show();
						$('.show-on-tab3').hide();
					}
				}
			});
			
			$('#date').datetimepicker({
				locale: 'ru',
				defaultDate: new Date(),
				format: 'DD.MM.YYYY'
			});
			
			$('#date').on("dp.change", function(e) {
				reload_operations($('#date').data("date"));
	        });
			
			$('#item').autocomplete({
				serviceUrl: 'suggestions.php',
				triggerSelectOnValidInput: false,
				onSelect: function (suggestion) {
					selected = suggestion;
					checkItem($(this).val());
				}
			});
			
			$('#item').keypress(function (e) {
				if (e.which == 13 && $(this).val() != '') {
					checkItem($(this).val());
					return false;
				}
			});
			
			$('#check-modal .button-yes').click(function(){
				$('#check-modal').modal('hide');
				$('#quantity').focus();
			});
			
			$('#check-modal .button-no').click(function(){
				$('#check-modal').modal('hide');
				$('#item').focus();
			});
			
			$('#quantity').keydown(function(event) {
				if ((event.which >= 48 && event.which <= 57) || event.which == 190 || event.which == 188) {
					if ($(this).val().indexOf('.') != -1) {
						var parts = $(this).val().split('.');
						if (typeof parts[1] == 'string' && parts[1].length >= 3) {
							event.preventDefault();
						}
					}
					if ($(this).val().indexOf(',') != -1) {
						var parts = $(this).val().split(',');
						if (typeof parts[1] == 'string' && parts[1].length >= 3) {
							event.preventDefault();
						}
					}
				}
			});
			
			$('#quantity').keypress(function(event) {
				var regex = /[^0-9\.\, ]/g;
				if ($(this).val() != '' && regex.test($(this).val())) {
					$(this).parents('.form-group').addClass('has-error');
				} else {
					var floatVal = $(this).val().replace(',', '.');
					if (isNaN(floatVal) || floatVal < 0) {
						$(this).parents('.form-group').addClass('has-error');
					} else {
						$(this).parents('.form-group').removeClass('has-error');
						if ($(this).val() != '' && event.which == 13) {
                            var founded = false;
                            $('#table > tbody > tr').each(function () {
                                if ($('td:nth-child(3)', this).html() == $('#item').val()) {
                                    var quantity = 0;
                                    if (!editMode) {
                                        quantity = parseFloat($('td:nth-child(4)', this).html().replace(',', '.'));
                                        if (isNaN(quantity)) quantity = 0;
                                    }
                                    quantity += parseFloat($('#quantity').val().replace(',', '.').replace(' ', ''));
                                    $('td:nth-child(4)', this).hide().html(quantity.toString().replace('.', ',')).fadeIn();
                                    founded = true;
                                    editMode = false;
                                    return false;
                                }
                            });
                            if (!founded && selected) {
                                $('<tr><td>' + selected.data[0] + '</td><td>' + selected.data[1] + '</td><td>' + $('#item').val() + '</td><td>' + $('#quantity').val().replace('.', ',').replace(' ', '') + '</td><td class="text-center"><button class="btn btn-primary btn-xs button-delete-item"><span class="glyphicon glyphicon-remove"></span></button></td><td class="text-center"><button class="btn btn-primary btn-xs button-edit-item"><span class="glyphicon glyphicon-pencil"></span></button></td></tr>').prependTo('#table > tbody').hide().fadeIn();
                            }
                            $('#quantity').val('');
							$('#item').val('').focus();
							selected = null;
							event.preventDefault();
						}
					}
				}
				if (event.which == 13) event.preventDefault();
			});
			
			$(document).on('click', '.button-edit-item', function(e) {
				var item = $(this).parents('tr').find('td:nth-child(3)').html();
				var quantity = $(this).parents('tr').find('td:nth-child(4)').html();
				$('#item').val(item);
				$('#quantity').val('').focus();
				editMode = true;
				e.preventDefault();
			});
			
			$(document).on('click', '.button-delete-item', function(e) {
				$(this).parents('tr').fadeOut(300, function(){ $(this).remove(); });
				e.preventDefault();
			});
			
			$('#submit').click(function(e) {
				if ($('#operation').val() == '') {
					alert('Укажите операцию.');
					e.preventDefault();
					return false;
				}
				if ($('#date').data("date") == '') {
					alert('Укажите дату.');
					e.preventDefault();
					return false;
				}
				var items = [];
				$('#table > tbody > tr').each(function() {
					items.push({
						'code': $('td:nth-child(1)', this).html(),
						'barcode': $('td:nth-child(2)', this).html(),
						'title': $('td:nth-child(3)', this).html(),
						'quantity': $('td:nth-child(4)', this).html(),
					});
				});
				if (items.length == 0) {
					alert('Добавьте товары.');
					e.preventDefault();
					return false;
				}
				$(this).prop('disabled', true);
				$('.loading').css('display', 'inline-block');
				$.ajax({
					url: 'order.php',
					type: 'POST',
					data: {
						operation: $('#operation').val(),
						date: $('#date').data("date"),
						items: items,
						comment: $('#comment').val().replace('\n', '<br>')
					},
					success: function(data) {
						$('.loading').hide();
						$('#submit').prop('disabled', false);
						if (!pre_operation) $("#operation")[0].selectedIndex = 0;
						$('#table > tbody > tr').fadeOut(300, function(){ $(this).remove(); });
						$('#comment').val('');
						$('body,html').animate({ scrollTop: 0 }, 300);
					}
				});
				e.preventDefault();
			});
			
			$('#action').change(function() {
				$(this).parents('.form-group').removeClass('has-error');
				if ($(this).val() == 'whowork') {
					$('#group-amount').hide();
					$('#group-howmuch').show();
					$('#cashier').focus();
				} else {
					$('#group-howmuch').hide();
					$('#group-amount').show();
					if ($('#cashier').val() == '') {
						$('#cashier').focus();
					} else {
						$('#amount').focus();
					}
				}
				return false;
			});
			
			$('#cashier').change(function() {
				$(this).parents('.form-group').removeClass('has-error');
				if ($(this).val() != '') {
					if ($('#action').val() == 'whowork') {
						$('#howmuch').focus();
					} else {
						$('#amount').focus();
					}
				}
			});
			
			$('#howmuch').change(function() {
				$(this).parents('.form-group').removeClass('has-error');
				$('#form-operations').submit();
			});
			
			$('#amount, #comment2').change(function() {
				$(this).parents('.form-group').removeClass('has-error');
			});
			
			$('#amount').keypress(function(e) {
				if (e.which == 13 && $(this).val() != '') {
					if ($('#action').val() == 'payment') {
						$('#comment2').focus();
						return false;
					}
				}
			});
			
			$('#password-modal').on('shown.bs.modal', function () {
				$('#collector_password').focus();
			})
			
			$('#form-operations').submit(function(e) {
				e.preventDefault();
				if ($('#action').val() == '') {
					$('#action').parents('.form-group').addClass('has-error');
					alert('Укажите действие.');
					return false;
				}
				if ($('#cashier').val() == '') {
					$('#cashier').parents('.form-group').addClass('has-error');
					alert('Укажите продавца.');
					return false;
				}
				if ($('#action').val() != 'whowork') {
					var regex = /[^0-9\.]/g;
					var floatVal = $('#amount').val().replace(',', '.').replace(' ', '');
					if (floatVal == '' || regex.test(floatVal) || isNaN(floatVal) || floatVal < 0) {
						$('#amount').parents('.form-group').addClass('has-error');
						alert('Некорректная сумма.');
						return false;
					}
				} else {
					if ($('#howmuch').val() == '') {
						$('#howmuch').parents('.form-group').addClass('has-error');
						alert('Укажите сколько.');
						return false;
					}
				}
				if ($('#action').val() == 'payment') {
					if ($('#comment2').val() == '') {
						$('#comment2').parents('.form-group').addClass('has-error');
						alert('Укажите комментарий.');
						return false;
					}
				}
				if ($('#action').val() == 'withdraw') {
					$('#password-modal').modal('show');
				} else {
					if ($('#action').val() == 'whowork') {
						addWhoWork();
					} else {
						addOperation('');
					}
				}
			});
			
			$('#password-modal .button-cancel').click(function(){
				$('#password-modal').modal('hide');
			});
			
			$('#password-modal .button-ok').click(function(){
				$('#collector-password-form').submit();
			});
			
			$(document).on('submit', '#collector-password-form', function(e){
				e.preventDefault();
				var collectorPassword = $('#password-modal #collector_password').val();
				$('#password-modal').modal('hide');
				$('#password-modal #collector_password').val('');
				addOperation(collectorPassword);
			});
			
			$('#shop').change(function(){
				$('#file1').html('');
				$('#file2').html('');
				$('input[name="shop"]').val($(this).val());
				$.ajax({
					url: 'getreports.php',
					type: 'POST',
					data: 'shop=' + $(this).val(),
					dataType: 'json',
					success: function(data) {
						if (data.files1.length > 0) {
							for (i in data.files1) {
								$('#file1').append('<option value="' + data.files1[i] + '">' + data.files1[i] + '</option>');
							}
						}
						if (data.files2.length > 0) {
							for (i in data.files2) {
								$('#file2').append('<option value="' + data.files2[i] + '">' + data.files2[i] + '</option>');
							}
						}
					}
				});
			});
			
			$(document).on('submit', '#reports-auth', function(e){
				e.preventDefault();
				$.ajax({
					url: 'reports.php',
					type: 'POST',
					data: 'auth_pass=' + $('#reports_password').val(),
					success: function(data) {
						$('#ui-id-4').html(data);
					}
				});
			});
			
			var previousHour = 0;
			setInterval(function(){
				var realDate = new Date();
				var currentHour = realDate.getHours();
				if (currentHour == 0 && previousHour == 23) {
					$("#date").data('DateTimePicker').date(new Date());
				}
				previousHour = currentHour;
			}, 5000);
		});
		
		function reload_operations(date) {
			$.ajax({
				url: 'getoperations.php',
				type: 'POST',
				data: 'date=' + date,
				dataType: 'json',
				success: function(response) {
					$('#previous-end-amount').html(response.pea);
					$('#start-amount').html(response.sa);
					$('#end-amount').html(response.ea);
					$('#table-operations > tbody > tr').remove();
					$('#table-whoworked > tbody > tr').remove();
					if (response.operations.length > 0) {
						for (i in response.operations) {
							$('<tr><td>' + response.operations[i][2] + '</td><td>' + response.operations[i][3] + '</td><td>' + response.operations[i][4] + '</td><td>' + response.operations[i][5] + '</td></tr>').prependTo('#table-operations > tbody');
						}
					}
					if (response.whoworked.length > 0) {
						for (i in response.whoworked) {
							$('<tr><td>' + response.whoworked[i][2] + '</td><td>' + response.whoworked[i][3] + '</td><td>' + response.whoworked[i][4] + '</td></tr>').prependTo('#table-whoworked > tbody');
						}
					}
					$('span#bonus').innerHTML = response.bonus;
                    $('span#new-avg-check').innerHTML = response.nac."(".((response.oac>response.nac)?("".(response.nac-response.oac)):("+".(response.nac-response.oac))).")";
                    $('span#old-avg-check').innerHTML = response.oac;

				}
			});
		}
		
		function checkItem(item) {
			$.ajax({
				url: 'check.php',
				type: 'POST',
				data: 'query=' + item,
				dataType: 'json',
				success: function(data) {
					if (data.status == '1') {
                        $('#quantity').focus();
					} else if (data.status == '0') {
                        $('#check-modal').modal('show');
					} else if (data.status == '2') {
                        $('#item').autocomplete('disable');
						$('#item').val(data.item.value);
						$('#quantity').focus();
						$('#item').autocomplete('enable');
						selected = data.item;
					}
				}
			});
		}
		
		function addOperation(pass) {
			$('#tab1-submit').prop('disabled', true);
			$.ajax({
				url: 'addoperation.php',
				type: 'POST',
				data: {
					date: $('#date').data("date"),
					action: $('#action').val(),
					cashier: $('#cashier').val(),
					amount: $('#amount').val().replace('.', ',').replace(' ', ''),
					comment: $('#comment2').val().replace('\n', '<br>'),
					password: pass
				},
				success: function(data) {
					var response = $.parseJSON(data);
					$('#tab1-submit').prop('disabled', false);
					if (response.result == 'ok') {
						if (typeof response.pea != 'undefined') {
							$('#previous-end-amount').html(response.pea);
						}
						if ($('#action').val() == 'start') {
							$('#start-amount').html($('#amount').val().replace('.', ',').replace(' ', ''));
							$('#end-amount').html('-');
						}
						if ($('#action').val() == 'end') {
							$('#end-amount').html($('#amount').val().replace('.', ',').replace(' ', ''));
						}
						if (typeof response.operations != 'undefined') {
							$('#table-operations > tbody > tr').remove();
							for (i in response.operations) {
								var tr = $('<tr><td>' + response.operations[i][2] + '</td><td>' + response.operations[i][3] + '</td><td>' + response.operations[i][4] + '</td><td>' + response.operations[i][5] + '</td></tr>').prependTo('#table-operations > tbody');
								if (i == response.operations.length - 1) {
									tr.hide().fadeIn()
									$({alpha:1}).animate({alpha:0}, {
								        duration: 3000,
								        step: function(){
								            tr.css('background-color','rgba(125,175,220,'+this.alpha+')');
								        }
								    });
								}
							}
						}
						$('#amount').val('');
						$('#comment2').val('');
						$('#action').focus();
					}
					if (response.result == 'wrong_password') {
						alert('Неверный пароль.');
						return false;
					}
				}
			});
		}
		
		function addWhoWork() {
			$('#tab1-submit').prop('disabled', true);
			$.ajax({
				url: 'addwhowork.php',
				type: 'POST',
				data: {
					date: $('#date').data("date"),
					cashier: $('#cashier').val(),
					howmuch: $('#howmuch').val().replace('.', ',').replace(' ', ''),
					comment: $('#comment2').val().replace('\n', '<br>')
				},
				success: function(data) {
					var response = $.parseJSON(data);
					$('#tab1-submit').prop('disabled', false);
					if (response.result == 'ok') {
						var tr = $('<tr><td>' + $('#cashier').val() + '</td><td>' + $('#howmuch').val().replace(',', '.').replace(' ', '') + '</td><td>' + $('#comment2').val().replace('\n', '<br>') + '</td></tr>').prependTo('#table-whoworked > tbody').hide().fadeIn();
						$({alpha:1}).animate({alpha:0}, {
					        duration: 3000,
					        step: function(){
					            tr.css('background-color','rgba(125,175,220,'+this.alpha+')');
					        }
					    });
						$('#table-whoworked > tbody > tr:gt(4)').fadeOut(300, function(){ $(this).remove(); });
						$('#howmuch').val('');
						$('#comment2').val('');
						$('#action').focus();
					}
				}
			});
		}
		
	</script>
	
	<div id="check-modal" class="modal fade" tabindex="-1" role="dialog">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-body">
	        <br/><p class="text-center">Такой товар отсутствует в списке.<br/>Подтверждаете ввод?</p>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default button-no">Нет</button>
	        <button type="button" class="btn btn-primary button-yes">Да</button>
	      </div>
	    </div>
	  </div>
	</div>
	
	<div id="password-modal" class="modal fade" tabindex="-1" role="dialog">
	  <div class="modal-dialog modal-sm">
	    <div class="modal-content">
	      <div class="modal-body">
	        <br/><p class="text-center">Введите пароль: <form method="post" action="" id="collector-password-form"><input type="password" id="collector_password" class="form-control" /></form></p>
	      </div>
	      <div class="modal-footer">
	    	<button type="button" class="btn btn-default button-cancel">Отмена</button>
	        <button type="button" class="btn btn-primary button-ok">Ok</button>
	      </div>
	    </div>
	  </div>
	</div>
</body>