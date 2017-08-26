<?php

class converter
{
    const BASE_URL = "https://api.deutschebahn.com/";
    const OPENDATA_BASE_URL = "https://transport.opendata.ch/v1";

    public function get_stationboard($access_token = "", $station_id, $date)
    {
        if (empty($access_token))
            throw new ErrorException("Access token cannot be empty!");

        else
            $request = \Httpful\Request::get(converter::BASE_URL . "fahrplan-plus/v1/departureBoard/$station_id?date=$date")
                ->addHeader('Authorization', 'Bearer ' . $access_token)
                ->send();
        $coordinates = $this->get_coordinates($access_token, $station_id, json_decode($request)[0]->stopName);
        $result = array();
        foreach (json_decode($request) as $item) {
            $data = array(
                'stop' => array(
                    'station' => array(
                        'id' => $item->stopId,
                        'name' => $item->stopName,
                        'score' => null,
                        'coordinate' => array(
                            'type' => 'WGS84',
                            'x' => null,
                            'y' => null
                        )
                    ),
                    'arrival' => null,
                    'arrivalTimestamp' => null,
                    'departure' => $item->dateTime,
                    'departureTimestamp' => date('U', strtotime($item->dateTime)),
                    'platform' => null,
                    'prognosis' => array(
                        "platform" => null,
                        "arrival" => null,
                        "departure" => null,
                        "capacity1st" => "-1",
                        "capacity2nd" => "-1"
                    )
                ),
                'name' => $item->name,
                'category' => null,
                'number' => preg_replace("/[^0-9]/", "", $item->name),
                'operator' => null,
                'to' => null
            );
            if (isset($item->track)) {
                $data['stop']['platform'] = $item->track;
            }
            if (isset($item->type)) {
                $data['category'] = $item->type;
            } else {
                $data['category'] = str_replace(" ", "", substr($item->name, 0, 3));
            }
            $temp_journey = $this->get_journey_details($access_token, $item->detailsId, $item->dateTime, $item->stopId);
            $data['operator'] = $temp_journey[count($temp_journey) - 1]['operator'];
            $data['to'] = $temp_journey[count($temp_journey) - 1]['stopName'];
            //$temp_station_details = $this->get_station_details($access_token, $item->stopId);

            $data['stop']['station']['coordinate']['x'] = $coordinates['x'];
            $data['stop']['station']['coordinate']['y'] = $coordinates['y'];

            array_push($result, $data);
        }

        return array('stationboard' => $result);
    }

    private function get_coordinates($access_token = "", $station_id = "", $station_name)
    {
        if (empty($station_name)) {
            return null;
        } else {
            $request = \Httpful\Request::get(converter::OPENDATA_BASE_URL . "/locations?query=" . urlencode($station_name))
                ->send();
            $result = json_decode($request, true);
            if (!empty($result['stations'][0]['coordinate']['x'])) {
                $result_set = array('x' => $result['stations'][0]['coordinate']['x'], 'y' => $result['stations'][0]['coordinate']['y']);
                return $result_set;
            } else {
                $temp = converter::get_station_details($access_token, $station_id);
                return converter::get_main_point($temp[0]->evaNumbers);
            }
        }

    }

    public function get_station_details($access_token, $station_id)
    {
        if (empty($access_token))
            throw new ErrorException("Access token cannot be empty!");
        else
            $request = \Httpful\Request::get(converter::BASE_URL . "stada/v2/stations?eva=$station_id")
                ->addHeader('Authorization', 'Bearer ' . $access_token)
                ->send();
        return json_decode($request->raw_body)->result;
    }

    private function get_main_point($evaNumbers)
    {
        $result_set = array('x' => null, 'y' => null);
        foreach ($evaNumbers as $item) {
            if ($item->isMain === true) {
                $result_set = array('x' => $item->geographicCoordinates->coordinates[1], 'y' => $item->geographicCoordinates->coordinates[0]);
            }
        }
        return $result_set;
    }

    public function get_journey_details($access_token, $journey_details, $timestamp = "", $stationId = "")
    {
        if (empty($access_token)) {
            throw new ErrorException("Access token cannot be empty!");
        }

        if (!empty($timestamp) AND !empty($stationId)) {
            if (file_exists('cache/' . md5($timestamp . $stationId) . '.json')) {
                if (time() - filemtime('cache/' . md5($timestamp . $stationId) . '.json') > 8 * 86400) {
                    $request = \Httpful\Request::get(converter::BASE_URL . "fahrplan-plus/v1/journeyDetails/" . urlencode($journey_details))
                        ->addHeader('Authorization', 'Bearer ' . $access_token)
                        ->send();
                    file_put_contents('cache/' . md5($timestamp . $stationId) . '.json', $request->raw_body);
                    return json_decode($request->raw_body, true);
                } else {
                    return json_decode(file_get_contents('cache/' . md5($timestamp . $stationId) . '.json'), true);
                }

            }
            if (!is_dir('cache/')) {
                mkdir('cache/');
                $request = \Httpful\Request::get(converter::BASE_URL . "fahrplan-plus/v1/journeyDetails/" . urlencode($journey_details))
                    ->addHeader('Authorization', 'Bearer ' . $access_token)
                    ->send();
                file_put_contents('cache/' . md5($timestamp . $stationId) . '.json', $request->raw_body);
                return json_decode($request->raw_body, true);
            } else {
                $request = \Httpful\Request::get(converter::BASE_URL . "fahrplan-plus/v1/journeyDetails/" . urlencode($journey_details))
                    ->addHeader('Authorization', 'Bearer ' . $access_token)
                    ->send();
                file_put_contents('cache/' . md5($timestamp . $stationId) . '.json', $request->raw_body);
                return json_decode($request->raw_body, true);
            }
        } else {
            $request = \Httpful\Request::get(converter::BASE_URL . "fahrplan-plus/v1/journeyDetails/" . urlencode($journey_details))
                ->addHeader('Authorization', 'Bearer ' . $access_token)
                ->send();
            return json_decode($request->raw_body, true);
        }
    }

    public function get_station_id_by_name($access_token, $station_name)
    {
        if (empty($access_token))
            throw new ErrorException("Access token cannot be empty!");
        else
            $request = \Httpful\Request::get(converter::BASE_URL . "fahrplan-plus/v1/location/" . str_replace(" ", "%20", $station_name))
                ->addHeader('Authorization', 'Bearer ' . $access_token)
                ->send();
        $request = json_decode($request, true);
        return $request[0]['id'];
    }
}