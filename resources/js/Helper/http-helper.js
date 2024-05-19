import axios from "axios";

export function httpGet(url) {
    return axios.get(url, {
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
    });
}

export function httpPost(url, data) {
    return new Promise((resolve, reject) => {
        axios
            .post(url, data, {
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
            })
            .then((response) => {
                resolve(response.data);
            })
            .catch((error) => {
                reject({ response, error: error.response.data });
            });
    });
}
